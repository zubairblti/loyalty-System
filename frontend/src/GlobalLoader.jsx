import { useEffect, useRef, useState } from 'react'

export default function GlobalLoader() {
  const [visible, setVisible] = useState(false)
  const active = useRef(0)
  const shownAt = useRef(0)
  const showTimer = useRef(null)
  const hideTimer = useRef(null)

  useEffect(() => {
    const receive = event => {
      active.current = event.detail.active
      window.clearTimeout(showTimer.current)
      window.clearTimeout(hideTimer.current)
      if (active.current > 0) {
        showTimer.current = window.setTimeout(() => {
          if (active.current > 0) {
            shownAt.current = Date.now()
            setVisible(true)
          }
        }, 160)
        return
      }
      const remaining = Math.max(0, 320 - (Date.now() - shownAt.current))
      hideTimer.current = window.setTimeout(() => setVisible(false), remaining)
    }
    window.addEventListener('loyalty:loading', receive)
    return () => {
      window.removeEventListener('loyalty:loading', receive)
      window.clearTimeout(showTimer.current)
      window.clearTimeout(hideTimer.current)
    }
  }, [])

  if (!visible) return null
  return <div className="global-loader" role="status" aria-live="polite" aria-label="Processing">
    <div className="global-loader-indicator"><span/><strong>Processing</strong></div>
  </div>
}
