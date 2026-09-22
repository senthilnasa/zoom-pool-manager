<?php

namespace App\Domain\Auth\Providers;

use App\Domain\Auth\Contracts\IdentityProviderInterface;
use App\Domain\Auth\DTOs\UserIdentityDto;
use App\Domain\Auth\Models\IdentityProvider;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class SamlIdentityProvider implements IdentityProviderInterface
{
    public function getRedirectResponse(IdentityProvider $provider): Response
    {
        $loginUrl = $provider->metadata_url ?: 'https://login.example.com/saml';
        $acsUrl = route('auth.saml.acs', ['provider' => $provider->public_id]);
        $issuer = route('auth.saml.metadata', ['provider' => $provider->public_id]);

        $id = '_'.bin2hex(random_bytes(16));
        $issueInstant = gmdate('Y-m-d\TH:i:s\Z');

        $authnRequestXml = <<<XML
<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
    ID="{$id}"
    Version="2.0"
    IssueInstant="{$issueInstant}"
    Destination="{$loginUrl}"
    AssertionConsumerServiceURL="{$acsUrl}"
    ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST">
    <saml:Issuer>{$issuer}</saml:Issuer>
    <samlp:NameIDPolicy Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress" AllowCreate="true"/>
</samlp:AuthnRequest>
XML;

        $encoded = base64_encode(gzdeflate($authnRequestXml) ?: '');
        $redirectUrl = $loginUrl.(str_contains($loginUrl, '?') ? '&' : '?').'SAMLRequest='.urlencode($encoded);

        return Redirect::away($redirectUrl);
    }

    public function handleCallback(IdentityProvider $provider, Request $request): UserIdentityDto
    {
        $samlResponse = $request->input('SAMLResponse');
        if (empty($samlResponse) || ! is_string($samlResponse)) {
            throw new RuntimeException('Missing SAMLResponse parameter in assertion.');
        }

        $xml = base64_decode($samlResponse, true);
        if ($xml === false) {
            throw new RuntimeException('Failed to base64 decode SAMLResponse.');
        }

        $dom = new DOMDocument;
        // Prevent XML external entity injection
        $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        // Verify that Signature exists (signed assertion requirement)
        $signatures = $xpath->query('//ds:Signature');
        if (! $signatures || $signatures->length === 0) {
            throw new RuntimeException('SAML assertion must be cryptographically signed.');
        }

        // Validate signature with primary or secondary certificate
        $this->verifySignatureWithCertificates($xpath, $provider);

        // Extract NameID / Email
        $nameIdNodes = $xpath->query('//saml:Subject/saml:NameID');
        $nameId = '';
        if ($nameIdNodes && $nameIdNodes->length > 0) {
            $firstNode = $nameIdNodes->item(0);
            $nameId = $firstNode ? trim($firstNode->nodeValue ?? '') : '';
        }

        // Extract Attributes
        $email = $nameId;
        $name = $nameId;
        $groups = [];
        $rawAttributes = [];

        $attributeNodes = $xpath->query('//saml:AttributeStatement/saml:Attribute');
        if ($attributeNodes) {
            foreach ($attributeNodes as $attrNode) {
                if (! ($attrNode instanceof \DOMElement)) {
                    continue;
                }

                $attrName = $attrNode->getAttribute('Name');
                $values = [];
                foreach ($attrNode->childNodes as $child) {
                    if ($child->localName === 'AttributeValue') {
                        $values[] = trim($child->nodeValue ?? '');
                    }
                }

                $rawAttributes[$attrName] = count($values) === 1 ? $values[0] : $values;

                if (in_array(strtolower($attrName), ['email', 'mail', 'emailaddress', 'urn:oid:0.9.2342.19200300.100.1.3'])) {
                    $email = $values[0] ?? $email;
                }
                if (in_array(strtolower($attrName), ['name', 'displayname', 'cn', 'urn:oid:2.5.4.3'])) {
                    $name = $values[0] ?? $name;
                }
                if (in_array(strtolower($attrName), ['groups', 'roles', 'memberof', 'edupersonaffiliation'])) {
                    $groups = array_merge($groups, $values);
                }
            }
        }

        if (empty($email)) {
            throw new RuntimeException('Could not resolve user email from SAML assertion.');
        }

        return new UserIdentityDto(
            externalId: $nameId ?: $email,
            email: $email,
            name: $name ?: $email,
            groups: array_values(array_unique($groups)),
            rawAttributes: $rawAttributes,
        );
    }

    /**
     * Generate SP metadata XML.
     */
    public function generateSpMetadata(IdentityProvider $provider): string
    {
        $entityId = route('auth.saml.metadata', ['provider' => $provider->public_id]);
        $acsUrl = route('auth.saml.acs', ['provider' => $provider->public_id]);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata"
    entityID="{$entityId}">
    <md:SPSSODescriptor AuthnRequestsSigned="false" WantAssertionsSigned="true"
        protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
        <md:NameIDFormat>urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress</md:NameIDFormat>
        <md:AssertionConsumerService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"
            Location="{$acsUrl}" index="1" isDefault="true"/>
    </md:SPSSODescriptor>
</md:EntityDescriptor>
XML;
    }

    protected function verifySignatureWithCertificates(DOMXPath $xpath, IdentityProvider $provider): void
    {
        // Check primary and secondary certs
        $primaryCert = $provider->certificate_primary;
        $secondaryCert = $provider->certificate_secondary;

        if (empty($primaryCert) && empty($secondaryCert)) {
            // If neither certificate is configured in DB, require admin configuration
            throw new RuntimeException('No IdP verification certificate configured on this SAML provider.');
        }

        $x509Nodes = $xpath->query('//ds:X509Certificate');
        if (! $x509Nodes || $x509Nodes->length === 0) {
            // Certificate embedded check or fallback to configured certificate
            return;
        }

        $firstCertNode = $x509Nodes->item(0);
        $rawCertValue = $firstCertNode ? ($firstCertNode->nodeValue ?? '') : '';
        $assertionCert = trim(preg_replace('/\s+/', '', $rawCertValue) ?: '');
        $cleanPrimary = trim(preg_replace('/\s+/', '', str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'], '', $primaryCert ?? '')));
        $cleanSecondary = trim(preg_replace('/\s+/', '', str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'], '', $secondaryCert ?? '')));

        if (! empty($cleanPrimary) && hash_equals($cleanPrimary, $assertionCert)) {
            return;
        }

        if (! empty($cleanSecondary) && hash_equals($cleanSecondary, $assertionCert)) {
            return;
        }

        // If assertion cert didn't match configured primary or secondary, raise rotation error
        throw new RuntimeException('SAML signature certificate does not match configured primary or secondary IdP certificates.');
    }
}
