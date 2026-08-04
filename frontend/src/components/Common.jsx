import { useEffect, useState } from 'react'
import { Eye, EyeOff, ShieldCheck } from 'lucide-react'
import { api } from '../api'
import { formatPhone } from '../phone'

export function PhoneInput({ name = 'phone', value, defaultValue, onChange, ...props }) {
  const [phone, setPhone] = useState(formatPhone(value ?? defaultValue ?? ''))
  useEffect(() => { if (value !== undefined) setPhone(formatPhone(value)) }, [value])
  return <input {...props} name={name} value={phone} inputMode="numeric" maxLength="13" placeholder="(0300)1234567" onChange={event => { const formatted = formatPhone(event.target.value); setPhone(formatted); onChange?.(event) }}/>
}

export function PasswordInput(props) {
  const [visible, setVisible] = useState(false)
  return <span className="password-input"><input {...props} type={visible ? 'text' : 'password'}/><button type="button" aria-label={visible ? 'Hide password' : 'Show password'} onClick={() => setVisible(value => !value)}>{visible ? <EyeOff size={18}/> : <Eye size={18}/>}</button></span>
}

export function SecurityForm({ endpoint = '/password', className = 'panel security-form' }) {
  const [feedback, setFeedback] = useState(null)
  const submit = async event => {
    event.preventDefault(); setFeedback(null)
    const form = event.currentTarget
    try { await api.put(endpoint, Object.fromEntries(new FormData(form))); form.reset(); setFeedback({ type: 'notice', message: 'Password updated successfully.' }) }
    catch (error) { setFeedback({ type: 'error', message: Object.values(error.response?.data?.errors || {})[0]?.[0] || error.response?.data?.message || 'Password could not be updated.' }) }
  }
  return <form className={className} onSubmit={submit}><div className="panel-head"><div><h2>Account security</h2><p>Use a unique password with at least 8 characters</p></div><ShieldCheck size={20}/></div><div className="security-fields"><label>Current password<PasswordInput name="current_password" autoComplete="current-password" required/></label><label>New password<PasswordInput name="password" autoComplete="new-password" minLength="8" required/></label><label>Confirm new password<PasswordInput name="password_confirmation" autoComplete="new-password" minLength="8" required/></label>{feedback && <div className={feedback.type}>{feedback.message}</div>}<button className="primary">Update password</button></div></form>
}

export function Pagination({ meta, page, onPage }) {
  const lastPage = meta?.last_page || 1
  if (lastPage <= 1) return null
  return <nav className="page-pagination" aria-label="Pagination"><button className="secondary" disabled={page <= 1} onClick={() => onPage(page - 1)}>Previous</button><span>Page {page} of {lastPage}</span><button className="secondary" disabled={page >= lastPage} onClick={() => onPage(page + 1)}>Next</button></nav>
}
