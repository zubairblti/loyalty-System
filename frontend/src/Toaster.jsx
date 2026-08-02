import { useEffect, useState } from 'react'
import { CheckCircle2, CircleAlert, Info, X } from 'lucide-react'

export default function Toaster() {
  const [items, setItems] = useState([])

  useEffect(() => {
    const receive = event => {
      const item = event.detail
      setItems(current => [...current.slice(-3), item])
      window.setTimeout(() => setItems(current => current.filter(entry => entry.id !== item.id)), 4500)
    }
    window.addEventListener('loyalty:toast', receive)
    return () => window.removeEventListener('loyalty:toast', receive)
  }, [])

  const remove = id => setItems(current => current.filter(item => item.id !== id))
  return <div className="toast-viewport" aria-live="polite">
    {items.map(item => {
      const Icon = item.type === 'success' ? CheckCircle2 : item.type === 'error' ? CircleAlert : Info
      return <div className={`toast-item ${item.type}`} key={item.id}>
        <Icon size={19}/><span>{item.message}</span><button title="Dismiss notification" onClick={() => remove(item.id)}><X size={15}/></button>
      </div>
    })}
  </div>
}
