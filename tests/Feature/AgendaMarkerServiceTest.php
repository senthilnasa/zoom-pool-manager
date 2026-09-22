<?php

namespace Tests\Feature;

use App\Domain\Meetings\Services\AgendaMarkerService;
use Tests\TestCase;

class AgendaMarkerServiceTest extends TestCase
{
    protected AgendaMarkerService $markerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markerService = new AgendaMarkerService;
    }

    public function test_embeds_marker_into_description(): void
    {
        $description = 'This is a team sync meeting.';
        $embedded = $this->markerService->appendMarker($description, '01HX1234567890ABCDEF');

        $this->assertStringContainsString('This is a team sync meeting.', $embedded);
        $this->assertStringContainsString('[ZPM:01HX1234567890ABCDEF]', $embedded);
    }

    public function test_does_not_duplicate_existing_marker(): void
    {
        $description = "This is a team sync meeting.\n\n[ZPM:01HX1234567890ABCDEF]";
        $embedded = $this->markerService->appendMarker($description, '01HX1234567890ABCDEF');

        $this->assertEquals($description, $embedded);
    }

    public function test_extracts_marker_id_correctly(): void
    {
        $description = "Weekly status update.\nImportant notes.\n[ZPM:01HX9999999999ABCDEF]\nThank you.";
        $extracted = $this->markerService->extractMarker($description);

        $this->assertEquals('01HX9999999999ABCDEF', $extracted);
    }

    public function test_returns_null_when_marker_is_not_present(): void
    {
        $description = 'External Zoom meeting without ZPM tracking.';
        $extracted = $this->markerService->extractMarker($description);

        $this->assertNull($extracted);
    }

    public function test_matches_meeting_identifier(): void
    {
        $description = "Class Lecture\n\n[ZPM:01HX1234567890ABCDEF]";
        $this->assertTrue($this->markerService->matchesMeeting($description, '01HX1234567890ABCDEF'));
        $this->assertFalse($this->markerService->matchesMeeting($description, '01HX9999999999ABCDEF'));
    }

    public function test_strips_marker_from_description(): void
    {
        $description = "Topic agenda line.\n\n[ZPM:01HX1234567890ABCDEF]";
        $stripped = $this->markerService->stripMarker($description);

        $this->assertEquals('Topic agenda line.', $stripped);
    }
}
