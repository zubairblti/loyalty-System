import { useEffect } from 'react'

export function useDismissable(open, onClose) {
  useEffect(() => {
    if (!open) return undefined
    const dismiss = event => {
      if (event.type === 'keydown' && event.key === 'Escape') onClose()
      if (event.type === 'pointerdown' && !event.target.closest('.workspace-actions')) onClose()
    }
    document.addEventListener('keydown', dismiss)
    document.addEventListener('pointerdown', dismiss)
    return () => { document.removeEventListener('keydown', dismiss); document.removeEventListener('pointerdown', dismiss) }
  }, [open, onClose])
}

export function useFocusTrap(active, selector) {
  useEffect(() => {
    if (!active) return undefined
    const container = document.querySelector(selector)
    const focusable = () => [...(container?.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [href]') || [])]
    focusable()[0]?.focus()
    const trap = event => {
      if (event.key !== 'Tab') return
      const items = focusable()
      if (!items.length) return
      const first = items[0]; const last = items.at(-1)
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
    }
    document.addEventListener('keydown', trap)
    return () => document.removeEventListener('keydown', trap)
  }, [active, selector])
}
