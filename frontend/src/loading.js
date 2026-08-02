let activeRequests = 0

function publish() {
  window.dispatchEvent(new CustomEvent('loyalty:loading', { detail: { active: activeRequests } }))
}

export function beginLoading() {
  activeRequests += 1
  publish()
}

export function endLoading() {
  activeRequests = Math.max(0, activeRequests - 1)
  publish()
}

export async function withGlobalLoading(callback) {
  beginLoading()
  try {
    return await callback()
  } finally {
    endLoading()
  }
}
