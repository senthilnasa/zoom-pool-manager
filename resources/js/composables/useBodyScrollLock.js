let lockCount = 0;
let originalOverflow = '';
let originalDocOverflow = '';
let originalPaddingRight = '';

export function lockScroll() {
  if (lockCount === 0) {
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    originalOverflow = document.body.style.overflow;
    originalDocOverflow = document.documentElement.style.overflow;
    originalPaddingRight = document.body.style.paddingRight;
    if (scrollbarWidth > 0) {
      document.body.style.paddingRight = `${scrollbarWidth}px`;
    }
    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';
  }
  lockCount++;
}

export function unlockScroll() {
  lockCount = Math.max(0, lockCount - 1);
  if (lockCount === 0) {
    document.body.style.overflow = originalOverflow || '';
    document.body.style.paddingRight = originalPaddingRight || '';
    document.documentElement.style.overflow = originalDocOverflow || '';
  }
}

export function resetScrollLock() {
  lockCount = 0;
  document.body.style.overflow = originalOverflow || '';
  document.body.style.paddingRight = originalPaddingRight || '';
  document.documentElement.style.overflow = originalDocOverflow || '';
}

export const vScrollLock = {
  mounted(el, binding) {
    if (binding.value === undefined || binding.value) {
      lockScroll();
      el._scrollLocked = true;
    }
  },
  updated(el, binding) {
    const shouldLock = binding.value === undefined || !!binding.value;
    if (shouldLock && !el._scrollLocked) {
      lockScroll();
      el._scrollLocked = true;
    } else if (!shouldLock && el._scrollLocked) {
      unlockScroll();
      el._scrollLocked = false;
    }
  },
  unmounted(el) {
    if (el._scrollLocked) {
      unlockScroll();
      el._scrollLocked = false;
    }
  },
};
