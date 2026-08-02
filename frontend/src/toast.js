export function toast(message, type = 'info') {
  window.dispatchEvent(new CustomEvent('loyalty:toast', { detail: { message, type, id: Date.now() } }))
}
