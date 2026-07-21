/**
 * Global autofocus tracker to prevent multiple simultaneous autofocus attempts
 * which cause the browser warning: "Autofocus processing was blocked because a document already has a focused element"
 */
let autofocusOccurred = false
let autofocusLocked = false
let autofocusCallbacks = []

export function requestAutofocus(callback) {
  if (autofocusOccurred && !autofocusLocked) {
    return false // Already focused somewhere else
  }

  if (autofocusLocked) {
    // Queue this callback for later
    autofocusCallbacks.push(callback)
    return false
  }

  autofocusLocked = true

  // Use requestAnimationFrame to ensure DOM is ready
  requestAnimationFrame(() => {
    callback()
    autofocusOccurred = true
    autofocusLocked = false

    // Process any queued callbacks
    if (autofocusCallbacks.length > 0) {
      const nextCallback = autofocusCallbacks.shift()
      requestAutofocus(nextCallback)
    }
  })

  return true
}

export function resetAutofocus() {
  autofocusOccurred = false
  autofocusLocked = false
  autofocusCallbacks = []
}

// Export a function to set up router reset hook
export function setupRouterAutofocusReset(router) {
  if (router) {
    router.afterEach(() => {
      // Small delay to ensure the next page has started mounting
      setTimeout(() => {
        resetAutofocus()
      }, 50)
    })
  }
}

// Vue directive for autofocus
export const autofocusDirective = {
  mounted(el) {
    requestAutofocus(() => {
      if (el.focus) {
        el.focus()
      } else if (el.querySelector('input, textarea, select')) {
        el.querySelector('input, textarea, select')?.focus()
      }
    })
  }
}
