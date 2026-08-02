import { Suspense, useCallback, useEffect, useRef, useState } from 'react'
import { CardCapture, PayerAuthentication } from '@sfpy/atoms'
import '@sfpy/atoms/styles'
import { QRCodeSVG } from 'qrcode.react'
import {
  BarChart3, Building2, CreditCard, Globe2, LayoutDashboard, LogOut, MonitorSmartphone,
  Plus, QrCode, ReceiptText, Search, Settings2, ShieldCheck, Users,
} from 'lucide-react'
import { api, login, prepareCsrf } from './api'
import { connectRealtime } from './realtime'
import { toast } from './toast'
import './styles.css'

const money = (value) => new Intl.NumberFormat('en-PK', { style: 'currency', currency: 'PKR', maximumFractionDigits: 0 }).format(value || 0)

function Login({ onLogin }) {
  const [mode, setMode] = useState('login')
  const [email, setEmail] = useState('')
  const [pendingEmail, setPendingEmail] = useState('')
  const [seconds, setSeconds] = useState(0)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const beginExpiry = expiresAt => setSeconds(Math.max(0, Math.ceil((new Date(expiresAt).getTime() - Date.now()) / 1000)))
  useEffect(() => {
    if (!seconds) return undefined
    const timer = window.setInterval(() => setSeconds(value => Math.max(0, value - 1)), 1000)
    return () => window.clearInterval(timer)
  }, [seconds])
  const switchMode = next => { setMode(next); setError(''); setNotice('') }
  const submitLogin = async e => {
    e.preventDefault(); setError('')
    const form = Object.fromEntries(new FormData(e.currentTarget))
    try { onLogin(await login(form.email, form.password)) } catch (err) { setError(err.response?.data?.message || 'Login failed') }
  }
  const submitRegister = async e => {
    e.preventDefault(); setError('')
    const form = Object.fromEntries(new FormData(e.currentTarget))
    try {
      await prepareCsrf()
      const { data } = await api.post('/register', form)
      setPendingEmail(form.email); beginExpiry(data.expires_at); setMode('verify')
    } catch (err) { setError(err.response?.data?.message || Object.values(err.response?.data?.errors || {})[0]?.[0] || 'Registration failed') }
  }
  const verify = async e => {
    e.preventDefault(); setError('')
    try { onLogin((await api.post('/register/verify', { email: pendingEmail, code: new FormData(e.currentTarget).get('code') })).data) }
    catch (err) { setError(err.response?.data?.message || 'Verification failed') }
  }
  const resend = async () => {
    try { const { data } = await api.post('/register/resend', { email: pendingEmail }); beginExpiry(data.expires_at); setNotice('A new code has been sent.') }
    catch (err) { setError(err.response?.data?.message || 'Could not resend code.') }
  }
  const forgot = async e => {
    e.preventDefault(); setError('')
    const value = new FormData(e.currentTarget).get('email')
    try { await prepareCsrf(); const { data } = await api.post('/forgot-password', { email: value }); setPendingEmail(value); beginExpiry(data.expires_at); setMode('reset') }
    catch (err) { setError(err.response?.data?.message || 'Account not found.') }
  }
  const reset = async e => {
    e.preventDefault(); setError('')
    const form = Object.fromEntries(new FormData(e.currentTarget))
    try { await api.post('/reset-password', { ...form, email: pendingEmail }); setNotice('Password updated. You can sign in now.'); setMode('login') }
    catch (err) { setError(err.response?.data?.message || 'Password reset failed.') }
  }
  return <main className="login-shell">
    <section className="auth-layout">
      <div className="auth-context">
        <div className="auth-brand"><span className="brand-mark">L</span><strong>LoyaltyOS</strong></div>
        <div><span className="auth-kicker">LOYALTY OPERATIONS</span><h1>One workspace for every customer relationship.</h1><p>Run points, POS, QR claims and connected checkout rewards from a single business account.</p></div>
        <div className="auth-trust"><span><ShieldCheck size={17}/>Secure tenant workspace</span><span><CreditCard size={17}/>Flexible subscription billing</span></div>
      </div>
      <div className="auth-form-wrap">
        {mode === 'login' && <form className="auth-form" onSubmit={submitLogin}>
          <div><h2>Business sign in</h2><p>Access your LoyaltyOS workspace</p></div>
          <label>Email address<input name="email" value={email} onChange={e => setEmail(e.target.value)} type="email" required placeholder="you@business.com"/></label>
          <label>Password<input name="password" type="password" required placeholder="Enter your password"/></label>
          <div className="auth-row"><label className="check-label"><input type="checkbox"/>Remember me</label><button type="button" className="text-button" onClick={() => switchMode('forgot')}>Forgot password?</button></div>
          {notice && <div className="notice auth-notice">{notice}</div>}{error && <div className="error">{error}</div>}
          <button className="primary auth-submit">Sign in</button>
          <p className="auth-switch">New to LoyaltyOS? <button type="button" onClick={() => switchMode('register')}>Create a business account</button></p>
        </form>}
        {mode === 'register' && <form className="auth-form register-form" onSubmit={submitRegister}>
          <div><h2>Create your business account</h2><p>Email verification is required before setup</p></div>
          <div className="auth-fields">
            <label>Business name<input name="business_name" required placeholder="Acme Store"/></label>
            <label>Full name<input name="name" required placeholder="Your full name"/></label>
            <label>Email address<input name="email" type="email" required placeholder="you@business.com"/></label>
            <label>Mobile number<input name="phone" required placeholder="+92 300 1234567"/></label>
            <label>Password<input name="password" type="password" minLength="8" required placeholder="Minimum 8 characters"/></label>
            <label>Confirm password<input name="password_confirmation" type="password" minLength="8" required placeholder="Repeat password"/></label>
          </div>
          {error && <div className="error">{error}</div>}
          <button className="primary auth-submit">Create account</button>
          <p className="auth-switch">Already registered? <button type="button" onClick={() => switchMode('login')}>Sign in</button></p>
        </form>}
        {mode === 'verify' && <form className="auth-form code-form" onSubmit={verify}>
          <div className="mail-icon">@</div><div><h2>Verify your email</h2><p>Enter the 6-digit code sent to <strong>{pendingEmail}</strong></p></div>
          <label>Verification code<input name="code" className="code-input" inputMode="numeric" maxLength="6" required placeholder="000000"/></label>
          <div className="code-expiry">{seconds ? `Code expires in ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}` : 'Code expired'}</div>
          {notice && <div className="notice auth-notice">{notice}</div>}{error && <div className="error">{error}</div>}
          <button className="primary auth-submit">Verify and continue</button>
          <button type="button" className="text-button" disabled={seconds > 0} onClick={resend}>Resend code</button>
        </form>}
        {mode === 'forgot' && <form className="auth-form" onSubmit={forgot}>
          <div><h2>Reset your password</h2><p>We will send a 2-minute verification code</p></div>
          <label>Email address<input name="email" type="email" required placeholder="you@business.com"/></label>
          {error && <div className="error">{error}</div>}<button className="primary auth-submit">Send reset code</button>
          <button type="button" className="text-button" onClick={() => switchMode('login')}>Back to sign in</button>
        </form>}
        {mode === 'reset' && <form className="auth-form" onSubmit={reset}>
          <div><h2>Set a new password</h2><p>Code sent to {pendingEmail}</p></div>
          <label>Verification code<input name="code" inputMode="numeric" maxLength="6" required/></label>
          <label>New password<input name="password" type="password" minLength="8" required/></label>
          <label>Confirm password<input name="password_confirmation" type="password" minLength="8" required/></label>
          <div className="code-expiry">{seconds ? `Expires in ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}` : 'Code expired'}</div>
          {error && <div className="error">{error}</div>}<button className="primary auth-submit">Update password</button>
        </form>}
      </div>
    </section>
  </main>
}

function AdminLogin({ onLogin }) {
  const [email, setEmail] = useState('admin@example.com')
  const [password, setPassword] = useState('password')
  const [error, setError] = useState('')
  const submit = async e => {
    e.preventDefault()
    try {
      const account = await login(email, password)
      if (account.role !== 'super_admin') {
        await api.post('/logout')
        setError('This account does not have Super Admin access.')
        return
      }
      onLogin(account)
    } catch (err) { setError(err.response?.data?.message || 'Login failed') }
  }
  return <main className="admin-login">
    <form className="admin-login-panel" onSubmit={submit}>
      <div className="admin-emblem"><ShieldCheck size={24}/></div>
      <div><small>LOYALTYOS CONTROL</small><h1>Super Admin</h1><p>Platform operations and tenant management</p></div>
      <label>Email<input type="email" value={email} onChange={e => setEmail(e.target.value)}/></label>
      <label>Password<input type="password" value={password} onChange={e => setPassword(e.target.value)}/></label>
      {error && <div className="error">{error}</div>}
      <button className="admin-primary">Sign in to control center</button>
    </form>
  </main>
}

function AdminPortal({ user, setUser }) {
  const [data, setData] = useState(null)
  const [view, setView] = useState('Overview')
  const [paymentFilters, setPaymentFilters] = useState({ business: '', status: '', from: '', to: '' })
  const [selectedBusiness, setSelectedBusiness] = useState(null)
  const [message, setMessage] = useState('')
  const load = () => api.get('/admin/dashboard').then(r => setData(r.data))
  useEffect(() => { if (user?.role === 'super_admin') load() }, [user])
  if (!user) return <AdminLogin onLogin={setUser}/>
  if (user.role !== 'super_admin') return <AdminLogin onLogin={setUser}/>
  if (!data) return <div className="loading full">Loading platform...</div>

  const toggleBusiness = async business => {
    await api.patch(`/admin/businesses/${business.id}`, { active: !business.active })
    load()
  }
  const logout = async () => { await api.post('/logout'); setUser(null) }
  const reviewPayment = async (payment, status) => {
    await api.post(`/admin/payments/${payment.id}/review`, { status })
    setMessage(`Payment ${status}.`); load()
  }
  const recordCash = async business => {
    const plan = data.plans[0]
    if (!plan) return setMessage('Create the platform plan first.')
    await api.post(`/admin/businesses/${business.id}/cash-payment`, { plan_id: plan.id, billing_cycle: 'monthly' })
    setMessage(`Cash subscription activated for ${business.name}.`); load()
  }
  const savePlan = async e => {
    e.preventDefault()
    const form = Object.fromEntries(new FormData(e.currentTarget))
    const payload = {
      ...form,
      monthly_price: Number(form.monthly_price),
      yearly_discount_percent: Number(form.yearly_discount_percent),
      domain_limit: Number(form.domain_limit),
      qr_limit: Number(form.qr_limit),
      terminal_limit: Number(form.terminal_limit),
      monthly_order_limit: Number(form.monthly_order_limit),
      features: form.features.split('\n').map(value => value.trim()).filter(Boolean),
    }
    try { await api.put('/admin/plan', payload); setMessage('Plan saved.'); load() }
    catch (err) { setMessage(err.response?.data?.message || 'Plan could not be saved.') }
  }
  const metrics = [
    ['Total businesses', data.metrics.businesses, Building2],
    ['Active businesses', data.metrics.active_businesses, ShieldCheck],
    ['Customers', data.metrics.customers, Users],
    ['Orders processed', data.metrics.orders, ReceiptText],
    ['Processed value', money(data.metrics.revenue_processed), CreditCard],
  ]
  const filteredPayments = (data.payments || []).filter(payment => {
    const date = payment.created_at?.slice(0, 10)
    return (!paymentFilters.business || String(payment.business_id) === paymentFilters.business)
      && (!paymentFilters.status || payment.status === paymentFilters.status)
      && (!paymentFilters.from || date >= paymentFilters.from)
      && (!paymentFilters.to || date <= paymentFilters.to)
  })
  return <div className="admin-shell">
    <aside className="admin-sidebar">
      <div className="admin-brand"><span className="admin-emblem small"><ShieldCheck size={18}/></span><div><strong>LoyaltyOS</strong><small>Control center</small></div></div>
      <nav><button className={view === 'Overview' ? 'active' : ''} onClick={() => setView('Overview')}><LayoutDashboard size={18}/>Platform overview</button><button className={view === 'Businesses' ? 'active' : ''} onClick={() => setView('Businesses')}><Building2 size={18}/>Businesses</button><button className={view === 'Payments' ? 'active' : ''} onClick={() => setView('Payments')}><ReceiptText size={18}/>Payments</button><button className={view === 'Plan' ? 'active' : ''} onClick={() => setView('Plan')}><CreditCard size={18}/>Plan</button></nav>
      <div className="admin-account"><div><strong>{user.name}</strong><small>Super Administrator</small></div><button title="Log out" onClick={logout}><LogOut size={18}/></button></div>
    </aside>
    <main className="admin-workspace">
      <header><div><small className="admin-eyebrow">PLATFORM OPERATIONS</small><h1>{view}</h1><p>{view === 'Plan' ? 'Configure the single subscription offered to every business' : view === 'Payments' ? 'Reconciled subscription payments across every business' : 'Tenant health, subscriptions and usage across LoyaltyOS'}</p></div></header>
      {message && <div className="notice admin-notice">{message}</div>}
      {view === 'Overview' && <><section className="admin-metrics">{metrics.map(([label, value, Icon]) => <article key={label}><div><small>{label}</small><strong>{value}</strong></div><span><Icon size={19}/></span></article>)}</section>
      <div className="admin-columns">
        <section className="panel"><div className="panel-head"><div><h2>Businesses</h2><p>Recent tenant accounts and platform usage</p></div></div>
          <table><thead><tr><th>Business</th><th>Plan</th><th>Customers</th><th>Orders</th><th>Status</th><th></th></tr></thead>
            <tbody>{data.businesses.map(b => <tr key={b.id}><td><strong>{b.name}</strong><small className="table-sub">/{b.slug}</small></td><td>{b.plan?.name || 'Unsubscribed'}</td><td>{b.customers_count}</td><td>{b.orders_count}</td><td><span className={b.active ? 'status' : 'status disabled'}>{b.active ? 'Active' : 'Suspended'}</span></td><td className="right"><button className="table-action" onClick={() => { setSelectedBusiness(b); setView('Businesses') }}>View</button></td></tr>)}</tbody></table>
        </section>
        <section className="panel plan-panel"><div className="panel-head"><div><h2>Plans</h2><p>Current tenant distribution</p></div></div>
          <div className="plan-list">{data.plans.map(plan => <div key={plan.id}><span><CreditCard size={17}/></span><div><strong>{plan.name}</strong><small>{plan.domain_limit} domains · {plan.terminal_limit} terminals</small></div><b>{plan.businesses_count}</b></div>)}</div>
        </section>
      </div></>}
      {view === 'Businesses' && <div className="business-admin-grid">
        <section className="panel"><div className="panel-head"><div><h2>All businesses</h2><p>Accounts are created only through verified registration</p></div></div>
          <div className="tenant-list">{data.businesses.map(b => <button className={selectedBusiness?.id === b.id ? 'selected' : ''} key={b.id} onClick={() => setSelectedBusiness(b)}><span>{b.name[0]}</span><div><strong>{b.name}</strong><small>{b.plan?.name || 'Subscription required'} · {b.orders_count} orders</small></div><b>{b.active ? 'Active' : 'Suspended'}</b></button>)}
            {!data.businesses.length && <div className="customer-empty">No businesses have registered yet.</div>}</div>
        </section>
        <section className="panel tenant-detail">{selectedBusiness ? <>
          <div className="panel-head"><div><h2>{selectedBusiness.name}</h2><p>/customer/{selectedBusiness.slug}</p></div><button className="table-action" onClick={() => toggleBusiness(selectedBusiness)}>{selectedBusiness.active ? 'Suspend' : 'Activate'}</button></div>
          <div className="tenant-stats"><div><small>PLAN</small><strong>{selectedBusiness.plan?.name || 'None'}</strong></div><div><small>CUSTOMERS</small><strong>{selectedBusiness.customers_count}</strong></div><div><small>ORDERS</small><strong>{selectedBusiness.orders_count}</strong></div></div>
          <div className="detail-actions"><button className="secondary" onClick={() => recordCash(selectedBusiness)}>Record monthly cash payment</button></div>
          <div className="payment-history"><h3>Payment submissions</h3>{selectedBusiness.payments.map(payment => <div key={payment.id}><div><strong>{payment.method} · {money(payment.amount)}</strong><small>{payment.transaction_reference || (payment.card_last_four ? `Card •••• ${payment.card_last_four}` : 'Cash')} · {payment.billing_cycle}</small>{payment.receipt_path && <a href={`/api/admin/payments/${payment.id}/receipt`} target="_blank" rel="noreferrer">View receipt</a>}</div><span className={`status ${payment.status === 'rejected' ? 'disabled' : payment.status === 'pending' ? 'pending' : ''}`}>{payment.status}</span></div>)}
            {!selectedBusiness.payments.length && <p>No payment submissions.</p>}</div>
        </> : <div className="setup"><Building2/><h3>Select a business</h3><p>Review subscription and payment information.</p></div>}</section>
        <section className="panel pending-review"><div className="panel-head"><div><h2>Pending approvals</h2><p>Verify payment ID and screenshot before approval</p></div></div>
          <div className="review-list">{data.pending_payments.map(payment => <div key={payment.id}><div><strong>{payment.business.name}</strong><small>{payment.method} · {payment.transaction_reference || `Card •••• ${payment.card_last_four}`} · {money(payment.amount)}</small></div><div><button className="table-action reject" onClick={() => reviewPayment(payment, 'rejected')}>Reject</button><button className="primary small-action" onClick={() => reviewPayment(payment, 'approved')}>Approve</button></div></div>)}
            {!data.pending_payments.length && <div className="customer-empty">No payments waiting for review.</div>}</div>
        </section>
      </div>}
      {view === 'Payments' && <div className="admin-payments">
        <section className="payment-metrics">
          <article><small>APPROVED REVENUE</small><strong>{money(data.payment_metrics?.approved_total)}</strong></article>
          <article><small>SAFEPAY CARD REVENUE</small><strong>{money(data.payment_metrics?.card_total)}</strong></article>
          <article><small>SUCCESSFUL PAYMENTS</small><strong>{data.payment_metrics?.approved_count || 0}</strong></article>
          <article><small>PROCESSING VALUE</small><strong>{money(data.payment_metrics?.processing_total)}</strong></article>
        </section>
        <section className="panel payments-ledger">
          <div className="panel-head"><div><h2>Payment ledger</h2><p>Safepay, cash and manually reviewed subscription payments</p></div><span className="status">{filteredPayments.length} records</span></div>
          <div className="payment-filters">
            <label>Business<select value={paymentFilters.business} onChange={e => setPaymentFilters({ ...paymentFilters, business: e.target.value })}><option value="">All businesses</option>{data.businesses.map(b => <option key={b.id} value={b.id}>{b.name}</option>)}</select></label>
            <label>Status<select value={paymentFilters.status} onChange={e => setPaymentFilters({ ...paymentFilters, status: e.target.value })}><option value="">All statuses</option><option value="approved">Approved</option><option value="processing">Processing</option><option value="pending">Pending</option><option value="rejected">Rejected</option></select></label>
            <label>From<input type="date" value={paymentFilters.from} onChange={e => setPaymentFilters({ ...paymentFilters, from: e.target.value })}/></label>
            <label>To<input type="date" value={paymentFilters.to} onChange={e => setPaymentFilters({ ...paymentFilters, to: e.target.value })}/></label>
            <button className="secondary" onClick={() => setPaymentFilters({ business: '', status: '', from: '', to: '' })}>Clear filters</button>
          </div>
          <table><thead><tr><th>Business</th><th>Amount</th><th>Method</th><th>Billing</th><th>Tracker / reference</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>{filteredPayments.map(payment => <tr key={payment.id}>
              <td><strong>{payment.business?.name || 'Unknown'}</strong><small className="table-sub">#{payment.id}</small></td>
              <td><strong>{money(payment.amount)}</strong></td>
              <td className="capitalize">{payment.method}</td><td className="capitalize">{payment.billing_cycle}</td>
              <td><code className="tracker-code">{payment.safepay_tracker || payment.transaction_reference || 'Cash'}</code>{payment.card_last_four && <small className="table-sub">Card ending {payment.card_last_four}</small>}</td>
              <td>{new Date(payment.created_at).toLocaleString()}</td>
              <td><span className={`status ${payment.status === 'rejected' ? 'disabled' : payment.status === 'processing' || payment.status === 'pending' ? 'pending' : ''}`}>{payment.status}</span></td>
            </tr>)}
            {!filteredPayments.length && <tr><td colSpan="7" className="customer-empty">No payments match these filters.</td></tr>}</tbody>
          </table>
        </section>
      </div>}
      {view === 'Plan' && <section className="plan-editor">
        <form className="panel" onSubmit={savePlan}>
          <div className="panel-head"><div><h2>Platform subscription plan</h2><p>One plan with monthly and discounted yearly billing</p></div><span className="status">Single plan</span></div>
          {(() => { const plan = data.plans[0] || {}; return <div className="plan-form-grid">
            <label>Plan name<input name="name" required defaultValue={plan.name || 'LoyaltyOS Business'}/></label>
            <label>Monthly price (PKR)<input name="monthly_price" type="number" min="0" required defaultValue={plan.monthly_price || 5000}/></label>
            <label>Yearly discount (%)<input name="yearly_discount_percent" type="number" min="0" max="90" required defaultValue={plan.yearly_discount_percent ?? 30}/></label>
            <label>Verified domains<input name="domain_limit" type="number" min="0" required defaultValue={plan.domain_limit ?? 1}/></label>
            <label>POS terminals<input name="terminal_limit" type="number" min="0" required defaultValue={plan.terminal_limit ?? 1}/></label>
            <label>QR codes<input name="qr_limit" type="number" min="0" required defaultValue={plan.qr_limit ?? 10}/></label>
            <label>Monthly orders<input name="monthly_order_limit" type="number" min="1" required defaultValue={plan.monthly_order_limit ?? 1000}/></label>
            <label className="features-field">Features, one per line<textarea name="features" rows="7" required defaultValue={(plan.features || ['Points ledger and customer rewards', 'POS and QR claims', 'Website checkout integration', 'Business analytics']).join('\n')}/></label>
            <div className="yearly-preview"><small>YEARLY PRICE PREVIEW</small><strong>{money((plan.monthly_price || 5000) * 12 * (1 - (plan.yearly_discount_percent ?? 30) / 100))}</strong><span>includes {plan.yearly_discount_percent ?? 30}% discount</span></div>
            <button className="admin-primary plan-save">Save platform plan</button>
          </div> })()}
        </form>
      </section>}
    </main>
  </div>
}

function ClaimPage({ token }) {
  const [state, setState] = useState('form')
  const [error, setError] = useState('')
  const submit = async e => {
    e.preventDefault()
    const values = Object.fromEntries(new FormData(e.currentTarget))
    try { await api.post(`/qr/${token}/claim`, values); setState('done') }
    catch (err) { setError(err.response?.data?.message || 'This code could not be claimed.') }
  }
  return <main className="login-shell">
    <section className="login-panel">
      <div className="brand-mark">L</div>
      {state === 'done' ? <><h1>Points claimed</h1><p>Your loyalty account has been updated.</p></> : <>
        <h1>Claim your points</h1><p>Enter the phone number linked to your loyalty account.</p>
        <form onSubmit={submit} className="claim-form">
          <label>Phone number<input name="phone" required placeholder="+92 300 1234567"/></label>
          <label>Name<input name="name" placeholder="Optional"/></label>
          {error && <div className="error">{error}</div>}
          <button className="primary">Claim points</button>
        </form>
      </>}
    </section>
  </main>
}

function CustomerPortal({ slug }) {
  const [business, setBusiness] = useState(null)
  const [dashboard, setDashboard] = useState(null)
  const [authMode, setAuthMode] = useState('login')
  const [pendingEmail, setPendingEmail] = useState('')
  const [seconds, setSeconds] = useState(0)
  const [code, setCode] = useState('')
  const [error, setError] = useState('')
  const [portalView, setPortalView] = useState('rewards')
  const [profileFeedback, setProfileFeedback] = useState(null)
  const [phoneCodeSent, setPhoneCodeSent] = useState(false)
  const [phoneSeconds, setPhoneSeconds] = useState(0)
  const [newPhone, setNewPhone] = useState('')
  const reloadDashboard = () => api.get(`/customer/${slug}/dashboard`).then(r => setDashboard(r.data))

  useEffect(() => {
    api.get(`/customer/${slug}/business`).then(r => setBusiness(r.data)).catch(() => setError('Business portal not found.'))
    api.get(`/customer/${slug}/dashboard`).then(r => setDashboard(r.data)).catch(() => {})
  }, [slug])

  useEffect(() => {
    if (!seconds) return undefined
    const timer = window.setInterval(() => setSeconds(value => Math.max(0, value - 1)), 1000)
    return () => window.clearInterval(timer)
  }, [seconds])

  useEffect(() => {
    if (!phoneSeconds) return undefined
    const timer = window.setInterval(() => setPhoneSeconds(value => Math.max(0, value - 1)), 1000)
    return () => window.clearInterval(timer)
  }, [phoneSeconds])

  const beginExpiry = expiresAt => setSeconds(Math.max(0, Math.ceil((new Date(expiresAt).getTime() - Date.now()) / 1000)))
  const switchAuthMode = mode => { setAuthMode(mode); setError(''); setCode('') }
  const registerCustomer = async e => {
    e.preventDefault(); setError('')
    const form = Object.fromEntries(new FormData(e.currentTarget))
    try {
      await prepareCsrf()
      const { data } = await api.post(`/customer/${slug}/register`, form)
      setPendingEmail(form.email); beginExpiry(data.expires_at); setAuthMode('verify')
    } catch (err) { setError(err.response?.data?.message || Object.values(err.response?.data?.errors || {})[0]?.[0] || 'Registration failed.') }
  }
  const verifyRegistration = async e => {
    e.preventDefault(); setError('')
    try {
      const { data } = await api.post(`/customer/${slug}/register/verify`, { email: pendingEmail, code })
      setDashboard(data)
    } catch (err) { setError(err.response?.data?.message || 'Invalid verification code.') }
  }
  const resendRegistration = async () => {
    setError('')
    try {
      const { data } = await api.post(`/customer/${slug}/register/resend`, { email: pendingEmail })
      beginExpiry(data.expires_at); toast('A new verification code has been emailed.', 'success')
    } catch (err) { setError(err.response?.data?.message || 'Code could not be resent.') }
  }
  const loginCustomer = async e => {
    e.preventDefault(); setError('')
    const form = Object.fromEntries(new FormData(e.currentTarget))
    try {
      await prepareCsrf()
      const { data } = await api.post(`/customer/${slug}/login`, form)
      setDashboard(data)
    } catch (err) { setError(err.response?.data?.message || 'Login failed.') }
  }
  const logout = async () => { await api.post(`/customer/${slug}/logout`); setDashboard(null); setAuthMode('login'); setCode('') }
  const updateProfile = async e => {
    e.preventDefault()
    try { await api.patch(`/customer/${slug}/profile`, Object.fromEntries(new FormData(e.currentTarget))); setProfileFeedback({ type: 'success', message: 'Profile updated.' }); reloadDashboard() }
    catch (err) { setProfileFeedback({ type: 'error', message: err.response?.data?.message || 'Profile update failed.' }) }
  }
  const requestPhoneCode = async () => {
    setProfileFeedback(null)
    try {
      const { data } = await api.post(`/customer/${slug}/profile/phone/otp`, { phone: newPhone })
      setPhoneCodeSent(true); setPhoneSeconds(data.expires_in || 120)
      setProfileFeedback({ type: 'success', message: 'A new verification code has been sent.' })
    } catch (err) { setProfileFeedback({ type: 'error', message: err.response?.data?.message || Object.values(err.response?.data?.errors || {})[0]?.[0] || 'Could not send code.' }) }
  }
  const verifyPhone = async e => {
    e.preventDefault()
    try { await api.post(`/customer/${slug}/profile/phone/verify`, { phone: newPhone, code: new FormData(e.currentTarget).get('code') }); setProfileFeedback({ type: 'success', message: 'Mobile number updated.' }); setPhoneCodeSent(false); setPhoneSeconds(0); reloadDashboard() }
    catch (err) { setProfileFeedback({ type: 'error', message: err.response?.data?.message || 'Verification failed.' }) }
  }

  if (!business && !error) return <div className="loading full">Loading rewards...</div>
  if (!dashboard) return <main className="customer-login">
    <section className="customer-login-panel">
      <div className="customer-brand"><span className="brand-mark">L</span><div><small>REWARDS BY LOYALTYOS</small><h1>{business?.name || 'Rewards'}</h1></div></div>
      <div className="login-copy"><h2>{authMode === 'register' ? 'Create your rewards account' : authMode === 'verify' ? 'Verify your email' : 'Welcome back'}</h2><p>{authMode === 'verify' ? `Enter the code sent to ${pendingEmail}.` : `Access your ${business?.name || ''} points and purchase history.`}</p></div>
      {authMode !== 'verify' && <div className="customer-auth-tabs"><button className={authMode === 'login' ? 'active' : ''} onClick={() => switchAuthMode('login')}>Sign in</button><button className={authMode === 'register' ? 'active' : ''} onClick={() => switchAuthMode('register')}>Register</button></div>}
      {authMode === 'login' && <form onSubmit={loginCustomer} className="claim-form">
        <label>Mobile number<input name="phone" required placeholder="+92 300 1234567"/></label>
        <label>Password<input name="password" type="password" required placeholder="Your password"/></label>
        {error && <div className="error">{error}</div>}
        <button className="customer-primary">Sign in</button>
      </form>}
      {authMode === 'register' && <form onSubmit={registerCustomer} className="claim-form">
        <label>Full name<input name="name" required placeholder="Your full name"/></label>
        <label>Email address<input name="email" type="email" required placeholder="you@example.com"/></label>
        <label>Mobile number<input name="phone" required placeholder="+92 300 1234567"/></label>
        <label>Password<input name="password" type="password" minLength="8" required placeholder="At least 8 characters"/></label>
        <label>Confirm password<input name="password_confirmation" type="password" minLength="8" required placeholder="Repeat your password"/></label>
        {error && <div className="error">{error}</div>}
        <button className="customer-primary">Create account</button>
      </form>}
      {authMode === 'verify' && <form onSubmit={verifyRegistration} className="claim-form">
        <label>6-digit code<input value={code} onChange={e => setCode(e.target.value)} required inputMode="numeric" maxLength="6" placeholder="000000"/></label>
        <small className="code-expiry">{seconds ? `Code expires in ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}` : 'Code expired. Request a new one.'}</small>
        {error && <div className="error">{error}</div>}
        <button className="customer-primary" disabled={!seconds}>Verify and continue</button>
        <button type="button" className="text-button" onClick={resendRegistration} disabled={seconds > 0}>Resend code</button>
        <button type="button" className="text-button" onClick={() => switchAuthMode('register')}>Change details</button>
      </form>}
      <p className="privacy-note">This account is only for rewards issued by {business?.name || 'this business'}.</p>
    </section>
  </main>

  const progress = dashboard.next_tier_at ? Math.min(100, (dashboard.balance / dashboard.next_tier_at) * 100) : 100
  return <main className="customer-portal">
    <header className="customer-header"><div className="customer-brand"><span className="brand-mark small">L</span><strong>{dashboard.business.name}</strong></div><nav className="customer-nav"><button className={portalView === 'rewards' ? 'active' : ''} onClick={() => setPortalView('rewards')}>My rewards</button><button className={portalView === 'profile' ? 'active' : ''} onClick={() => setPortalView('profile')}>Profile</button></nav><button className="text-button" onClick={logout}>Sign out</button></header>
    <section className="customer-content">
      <div className="customer-welcome"><div><p>Welcome back</p><h1>{dashboard.customer.name || dashboard.customer.phone}</h1></div><span className="tier-badge">{dashboard.tier}</span></div>
      {portalView === 'rewards' && <><section className="wallet">
        <small>AVAILABLE POINTS</small><strong>{dashboard.balance.toLocaleString()}</strong><p>Use your points on your next eligible purchase.</p>
        <div className="tier-progress"><div><span>{dashboard.tier}</span><span>{dashboard.next_tier || 'Top tier'}</span></div><progress value={progress} max="100"/>
          <small>{dashboard.next_tier ? `${Math.max(0, dashboard.next_tier_at - dashboard.balance)} points to ${dashboard.next_tier}` : 'You reached the highest tier'}</small></div>
      </section>
      <div className="customer-grid">
        <section className="customer-section"><div className="customer-section-head"><h2>Points activity</h2><span>{dashboard.transactions.length} entries</span></div>
          <div className="activity-list">{dashboard.transactions.map(tx => <div className="activity-row" key={tx.id}><div className={tx.points >= 0 ? 'activity-icon earn' : 'activity-icon spend'}>{tx.points >= 0 ? '+' : '−'}</div><div><strong>{tx.description || tx.type}</strong><small>{new Date(tx.created_at).toLocaleDateString()}{tx.order ? ` · ${money(tx.order.total)} purchase` : ''}</small></div><b className={tx.points >= 0 ? 'positive' : 'negative'}>{tx.points >= 0 ? '+' : ''}{tx.points} pts</b></div>)}
            {!dashboard.transactions.length && <div className="customer-empty">Your points activity will appear here.</div>}</div>
        </section>
        <section className="customer-section"><div className="customer-section-head"><h2>Recent purchases</h2><span>{dashboard.orders.length} orders</span></div>
          <div className="activity-list">{dashboard.orders.map(order => <div className="activity-row" key={order.id}><div className="activity-icon order"><ReceiptText size={17}/></div><div><strong>{order.external_id}</strong><small>{new Date(order.created_at).toLocaleDateString()} · {order.payment_method || order.source}</small></div><b>{money(order.total)}</b></div>)}
            {!dashboard.orders.length && <div className="customer-empty">No linked purchases yet.</div>}</div>
        </section>
      </div></>}
      {portalView === 'profile' && <div className="profile-grid">
        <form className="customer-section profile-form" onSubmit={updateProfile}>
          <div className="customer-section-head"><h2>Personal details</h2><span>Customer #{dashboard.customer.id}</span></div>
          <div className="profile-fields"><label>Full name<input name="name" required defaultValue={dashboard.customer.name || ''}/></label><label>Email address<input name="email" type="email" defaultValue={dashboard.customer.email || ''} placeholder="you@example.com"/></label><label>Current mobile<input value={dashboard.customer.phone} disabled/></label>
            <button className="customer-primary">Save profile</button></div>
        </form>
        <section className="customer-section phone-change"><div className="customer-section-head"><h2>Change mobile number</h2></div>
          <div className="profile-fields"><label>New mobile number<input value={newPhone} onChange={e => setNewPhone(e.target.value)} placeholder="+92 300 1234567"/></label>
            {!phoneCodeSent ? <button className="customer-primary" onClick={requestPhoneCode}>Send verification code</button> : <form onSubmit={verifyPhone} className="claim-form"><label>Verification code<input name="code" inputMode="numeric" maxLength="6" required/></label><small className="code-expiry">{phoneSeconds ? `Code expires in ${Math.floor(phoneSeconds / 60)}:${String(phoneSeconds % 60).padStart(2, '0')}` : 'Code expired. Request a new one.'}</small><button className="customer-primary" disabled={!phoneSeconds}>Verify number</button><button type="button" className="text-button" onClick={requestPhoneCode} disabled={phoneSeconds > 90}>Resend code{phoneSeconds > 90 ? ` in ${phoneSeconds - 90}s` : ''}</button></form>}
            {profileFeedback && <div className={`${profileFeedback.type === 'error' ? 'error' : 'notice'} auth-notice`}>{profileFeedback.message}</div>}</div>
        </section>
      </div>}
    </section>
  </main>
}

function SubscriptionGate({ user, status, onLogout }) {
  const [cycle, setCycle] = useState('monthly')
  const [checkout, setCheckout] = useState(null)
  const [authSession, setAuthSession] = useState(null)
  const [discountBody, setDiscountBody] = useState()
  const [loading, setLoading] = useState(false)
  const [cardReady, setCardReady] = useState(false)
  const [billing, setBilling] = useState({ street_1: '', city: '', postal_code: '', country: 'PK' })
  const cardRef = useRef(null)
  const authRef = useRef(null)
  const plan = status.plan
  const amount = plan ? (cycle === 'yearly' ? plan.monthly_price * 12 * (1 - plan.yearly_discount_percent / 100) : plan.monthly_price) : 0
  const beginCheckout = useCallback(async () => {
    if (!plan || !status.card_gateway?.configured) return
    setLoading(true); setCardReady(false); setCheckout(null)
    try {
      const { data } = await api.post('/subscription/safepay/checkout', { plan_id: plan.id, billing_cycle: cycle })
      setCheckout(data)
    } catch (error) {
      toast(error.response?.data?.errors?.payment?.[0] || error.response?.data?.message || 'Safepay checkout could not be started.', 'error')
    } finally { setLoading(false) }
  }, [cycle, plan, status.card_gateway?.configured])
  useEffect(() => { beginCheckout() }, [beginCheckout])

  const finishPayment = useCallback(async () => {
    setAuthSession(null); setLoading(true)
    try {
      for (let attempt = 0; attempt < 30; attempt += 1) {
        const { data } = await api.get(`/subscription/safepay/${checkout.tracker}`)
        if (data.active) {
          toast('Payment verified. Your workspace is now active.', 'success')
          window.location.reload()
          return
        }
        await new Promise(resolve => window.setTimeout(resolve, 2000))
      }
      toast('Payment is processing. Your workspace will activate after Safepay confirms it.', 'info')
    } catch (error) {
      toast(error.response?.data?.message || 'Payment status could not be verified.', 'error')
    } finally { setLoading(false) }
  }, [checkout])

  const failPayment = useCallback(data => {
    setAuthSession(null); setLoading(false)
    toast(data?.errorMessage || data?.error || data?.message || 'Card authentication failed. Please try another card.', 'error')
  }, [])

  const handleCardError = useCallback(error => {
    setLoading(false)
    const message = typeof error === 'string' ? error : error?.message || 'Secure card fields failed to load.'
    if (/401|unauthori[sz]ed|expired|token/i.test(message)) {
      toast('Secure payment session expired. Card fields have been refreshed; please enter the card again.', 'info')
      beginCheckout()
      return
    }
    toast(message, 'error')
  }, [beginCheckout])

  const submit = async e => {
    e.preventDefault()
    if (!status.card_gateway?.configured) {
      toast('Safepay Embedded Checkout credentials are required before live card payments can be processed.', 'error')
      return
    }
    if (!checkout || !cardRef.current) {
      toast('Secure card fields are still loading.', 'info')
      return
    }
    setLoading(true)
    cardRef.current.validate()
    const valid = await cardRef.current.fetchValidity()
    if (!valid) {
      setLoading(false)
      toast('Check the card details and try again.', 'error')
      return
    }
    cardRef.current.submit()
  }
  return <div className="subscription-lock">
    <div className="locked-topbar"><div className="brand"><span className="brand-mark small">L</span><strong>LoyaltyOS</strong></div><button className="text-button" onClick={onLogout}>Sign out</button></div>
    <section className="subscription-dialog">
      <div className="subscription-intro"><span className="lock-icon"><CreditCard size={23}/></span><div><small>WORKSPACE ACTIVATION</small><h1>Choose your subscription</h1><p>{user.business.name} is ready. Activate the plan to unlock POS, QR codes, integrations and customer rewards.</p></div></div>
      {!plan ? <div className="no-plan"><h2>No plan is available yet</h2><p>The Super Admin must configure the platform plan before businesses can activate a subscription.</p></div> : <>
        <div className="billing-toggle"><button className={cycle === 'monthly' ? 'active' : ''} onClick={() => setCycle('monthly')}>Monthly</button><button className={cycle === 'yearly' ? 'active' : ''} onClick={() => setCycle('yearly')}>Yearly <span>Save {plan.yearly_discount_percent}%</span></button></div>
        <div className="subscription-body">
          <div className="selected-plan"><div className="plan-title"><div><small>SINGLE PLATFORM PLAN</small><h2>{plan.name}</h2></div><div className="plan-price"><strong>{money(amount)}</strong><span>/{cycle === 'yearly' ? 'year' : 'month'}</span></div></div>
            <ul>{(plan.features || []).map(feature => <li key={feature}><span>✓</span>{feature}</li>)}</ul>
            <div className="plan-limits"><span>{plan.domain_limit} domains</span><span>{plan.terminal_limit} POS terminals</span><span>{plan.qr_limit} QR codes</span><span>{plan.monthly_order_limit.toLocaleString()} monthly orders</span></div>
          </div>
          <form className="payment-form card-checkout" onSubmit={submit}>
            <div className="checkout-heading"><div><h2>Pay securely by card</h2><p>Visa and Mastercard debit or credit cards</p></div><span><ShieldCheck size={17}/>Secure</span></div>
            <label>Card information
              <div className={`safepay-card-frame ${cardReady ? 'ready' : ''}`}>
                {loading && !checkout && <span className="safepay-loading">Opening secure card fields...</span>}
                {checkout && <Suspense fallback={<span className="safepay-loading">Loading Safepay...</span>}>
                  <CardCapture key={checkout.auth_token} environment={checkout.environment} authToken={checkout.auth_token} tracker={checkout.tracker}
                    validationEvent="submit" inputStyle={{ fontFamily: 'Inter, Arial, sans-serif', fontSize: '14px', color: '#14231d' }}
                    imperativeRef={cardRef} onReady={() => setCardReady(true)} onError={handleCardError}
                    onDiscountApplied={data => setDiscountBody(data?.discountBody)}
                    onProceedToAuthentication={data => {
                      setLoading(false)
                      setAuthSession({
                        accessToken: data.accessToken,
                        deviceDataCollectionURL: data.deviceDataCollectionURL,
                      })
                    }}/>
                </Suspense>}
              </div>
            </label>
            <div className="billing-fields">
              <label>Street address <small>Optional</small><input value={billing.street_1} onChange={e => setBilling({ ...billing, street_1: e.target.value })} autoComplete="billing street-address"/></label>
              <label>City <small>Optional</small><input value={billing.city} onChange={e => setBilling({ ...billing, city: e.target.value })} autoComplete="billing address-level2"/></label>
              <label>Postal code<input value={billing.postal_code} onChange={e => setBilling({ ...billing, postal_code: e.target.value })} autoComplete="billing postal-code"/></label>
              <label>Country<select value={billing.country} onChange={e => setBilling({ ...billing, country: e.target.value })}><option value="PK">Pakistan</option></select></label>
            </div>
            <div className="secure-note"><ShieldCheck size={17}/><span>Card details will be tokenized by the PCI-compliant provider and will never be stored on LoyaltyOS servers.</span></div>
            <div className="payment-total"><span>Amount due</span><strong>{money(amount)}</strong></div>
            {!status.card_gateway?.configured && <div className="gateway-state"><span></span><div><strong>Gateway setup required</strong><small>Add Safepay sandbox keys to enable test transactions.</small></div></div>}
            <button className="primary auth-submit" disabled={loading || !cardReady}>{loading ? 'Processing...' : `Pay ${money(amount)}`}</button>
            <p className="checkout-terms">By paying, you authorize LoyaltyOS to charge this card for the selected billing period.</p>
          </form>
        </div>
      </>}
    </section>
    {authSession && <div className="safepay-auth-backdrop">
      <div className="safepay-auth-modal">
        <Suspense fallback={<div className="safepay-loading">Opening bank verification...</div>}>
          <PayerAuthentication environment={checkout.environment} tracker={checkout.tracker} authToken={checkout.auth_token}
            deviceDataCollectionJWT={authSession.accessToken} deviceDataCollectionURL={authSession.deviceDataCollectionURL}
            user={checkout.user} billing={billing.street_1 && billing.city ? billing : undefined}
            discountBody={discountBody} authorizationOptions={{ do_capture: true, do_card_on_file: false }}
            imperativeRef={authRef} onPayerAuthenticationSuccess={finishPayment} onPayerAuthenticationFrictionless={finishPayment}
            onPayerAuthenticationFailure={failPayment} onPayerAuthenticationUnavailable={failPayment}
            onSafepayError={data => {
              setAuthSession(null)
              if (/401|unauthori[sz]ed|expired|token/i.test(data?.error?.message || '')) handleCardError(data.error)
              else failPayment(data?.error)
            }}/>
        </Suspense>
      </div>
    </div>}
  </div>
}

function Shell({ user, onLogout }) {
  const [view, setView] = useState('Overview')
  const [revision, setRevision] = useState(0)
  const [subscription, setSubscription] = useState(null)
  const loadSubscription = () => api.get('/subscription').then(r => setSubscription(r.data))
  useEffect(() => { loadSubscription() }, [])
  useEffect(() => connectRealtime(user.business.id, () => setRevision(value => value + 1)), [user.business.id])
  if (!subscription) return <div className="loading full">Loading subscription...</div>
  if (!subscription.subscription) return <SubscriptionGate user={user} status={subscription} onLogout={onLogout}/>
  const items = [
    ['Overview', LayoutDashboard], ['POS', ReceiptText], ['QR codes', QrCode],
    ['Integrations', Globe2], ['Customers', Users], ['Analytics', BarChart3],
  ]
  return <div className="app-shell">
    <aside>
      <div className="brand"><span className="brand-mark small">L</span><strong>LoyaltyOS</strong></div>
      <nav>{items.map(([name, Icon]) => <button key={name} className={view === name ? 'active' : ''} onClick={() => setView(name)}><Icon size={18} />{name}</button>)}</nav>
      <div className="aside-bottom">
        <button><Settings2 size={18} />Settings</button>
        <div className="account"><span>{user.name[0]}</span><div><strong>{user.name}</strong><small>{user.business.name}</small></div><button title="Log out" onClick={onLogout}><LogOut size={17}/></button></div>
      </div>
    </aside>
    <main className="workspace">
      <header><div><h1>{view}</h1><p>{user.business.name} · {subscription.subscription.plan.name} plan</p></div><button className="icon-button" title="Search"><Search size={19}/></button></header>
      {view === 'Overview' && <Overview revision={revision} customerUrl={subscription.customer_portal_url} />}
      {view === 'POS' && <Pos />}
      {view === 'QR codes' && <QRCodes />}
      {view === 'Integrations' && <Integrations plan={subscription.subscription.plan} />}
      {['Customers', 'Analytics'].includes(view) && <Empty title={view} />}
    </main>
  </div>
}

function Overview({ revision, customerUrl }) {
  const [data, setData] = useState(null)
  useEffect(() => { api.get('/dashboard').then(r => setData(r.data)) }, [revision])
  if (!data) return <div className="loading">Loading workspace...</div>
  const cards = [
    ['Revenue', money(data.metrics.revenue)], ['Paid orders', data.metrics.orders],
    ['Customers', data.metrics.customers], ['Points issued', data.metrics.points_issued],
  ]
  return <><section className="metrics">{cards.map(([k, v]) => <article key={k}><small>{k}</small><strong>{v}</strong></article>)}</section>
    <section className="portal-share"><div><Globe2 size={20}/><div><strong>Customer rewards portal</strong><small>Share this link in receipts, SMS, email or a QR code so customers can check points.</small></div></div><code>{customerUrl}</code><button className="secondary" onClick={() => navigator.clipboard.writeText(customerUrl)}>Copy link</button></section>
    <section className="panel"><div className="panel-head"><div><h2>Recent orders</h2><p>Latest activity across connected sales channels</p></div></div>
      <table><thead><tr><th>Order</th><th>Customer</th><th>Source</th><th>Status</th><th className="right">Total</th></tr></thead>
        <tbody>{data.recent_orders.map(o => <tr key={o.id}><td>{o.external_id}</td><td>{o.customer?.name || o.customer?.phone || 'Walk-in'}</td><td>{o.source}</td><td><span className="status">{o.status}</span></td><td className="right">{money(o.total)}</td></tr>)}
        {!data.recent_orders.length && <tr><td colSpan="5" className="empty-row">No orders yet. Create the first sale from POS.</td></tr>}</tbody></table>
    </section></>
}

function Pos() {
  const [terminals, setTerminals] = useState([])
  const [message, setMessage] = useState('')
  const load = () => api.get('/pos/terminals').then(r => setTerminals(r.data))
  useEffect(() => { load() }, [])
  const createTerminal = async () => { await api.post('/pos/terminals', { name: 'Main counter', branch: 'Main branch' }); load() }
  const submit = async e => {
    e.preventDefault(); const f = new FormData(e.currentTarget)
    try {
      const { data } = await api.post('/pos/sales', Object.fromEntries(f))
      setMessage(`Sale ${data.external_id} completed.`); e.currentTarget.reset()
    } catch (err) { setMessage(err.response?.data?.message || 'Sale failed') }
  }
  return <div className="two-col"><section className="panel form-panel"><div className="panel-head"><div><h2>New sale</h2><p>Record a counter payment and award points</p></div></div>
    {terminals.length ? <form onSubmit={submit} className="form-grid">
      <label>Terminal<select name="terminal_id">{terminals.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}</select></label>
      <label>Total (PKR)<input name="total" type="number" min="1" required placeholder="2,500" /></label>
      <label>Customer phone<input name="phone" required placeholder="+92 300 1234567" /></label>
      <label>Customer name<input name="customer_name" placeholder="Optional" /></label>
      <label>Payment<select name="payment_method"><option value="cash">Cash</option><option value="card">Card</option><option value="jazzcash">JazzCash</option><option value="easypaisa">EasyPaisa</option></select></label>
      <button className="primary wide">Complete sale</button>
    </form> : <div className="setup"><MonitorSmartphone/><h3>No terminal configured</h3><button className="primary" onClick={createTerminal}><Plus size={17}/>Add main terminal</button></div>}
    {message && <div className="notice">{message}</div>}
  </section><section className="panel tips"><h2>Transaction policy</h2><p>Points are issued only after a paid sale. Every order and ledger entry has an idempotency key, so retries cannot award points twice.</p><hr/><strong>Accepted methods</strong><p>Cash, card, JazzCash and EasyPaisa can be recorded. Gateway settlement requires merchant credentials.</p></section></div>
}

function QRCodes() {
  const [codes, setCodes] = useState([])
  const [created, setCreated] = useState(null)
  const load = () => api.get('/qr-codes').then(r => setCodes(r.data))
  useEffect(() => { load() }, [])
  const create = async () => { const r = await api.post('/qr-codes', { label: `Counter QR ${codes.length + 1}`, type: 'static' }); setCreated(r.data); load() }
  return <section className="panel"><div className="panel-head"><div><h2>Claim QR codes</h2><p>Plan-limited codes for counters, receipts and campaigns</p></div><button className="primary" onClick={create}><Plus size={17}/>Create QR</button></div>
    {created && <div className="qr-created"><QRCodeSVG value={created.claim_url} size={140}/><div><strong>{created.qr.label}</strong><p>Scan to open the secure claim flow.</p><code>{created.claim_url}</code></div></div>}
    <div className="list">{codes.map(q => <div className="list-row" key={q.id}><QrCode size={20}/><div><strong>{q.label}</strong><small>{q.type} · {q.claimed_at ? 'Claimed' : 'Active'}</small></div><span className="status">{q.type}</span></div>)}</div>
  </section>
}

function Integrations({ plan }) {
  const [data, setData] = useState({ domains: [], integrations: [] })
  const [url, setUrl] = useState('')
  const [secret, setSecret] = useState('')
  const load = () => api.get('/integrations').then(r => setData(r.data))
  useEffect(() => { load() }, [])
  const addDomain = async e => { e.preventDefault(); await api.post('/domains', { url }); setUrl(''); load() }
  const addKey = async () => { const r = await api.post('/integrations', { name: 'Website checkout' }); setSecret(r.data.secret); load() }
  return <div className="stack">
    <section className="panel"><div className="panel-head"><div><h2>Verified domains</h2><p>{data.domains.length} of {plan.domain_limit} used</p></div></div>
      <form className="inline-form" onSubmit={addDomain}><input value={url} onChange={e => setUrl(e.target.value)} required type="url" placeholder="https://shop.example.com"/><button className="primary"><Plus size={17}/>Add domain</button></form>
      <div className="list">{data.domains.map(d => <div className="list-row" key={d.id}><Globe2 size={20}/><div><strong>{d.host}</strong><small>{d.verification_token}</small></div><span className={d.verified_at ? 'status' : 'status pending'}>{d.verified_at ? 'Verified' : 'Pending'}</span></div>)}</div>
    </section>
    <section className="panel"><div className="panel-head"><div><h2>API connections</h2><p>HMAC-signed checkout and backend order events</p></div><button className="secondary" onClick={addKey}><Plus size={17}/>Create key</button></div>
      {secret && <div className="secret"><strong>Copy this secret now</strong><code>{secret}</code></div>}
      <div className="list">{data.integrations.map(i => <div className="list-row" key={i.id}><MonitorSmartphone size={20}/><div><strong>{i.name}</strong><small>{i.public_key}</small></div><span className="status">{i.active ? 'Active' : 'Disabled'}</span></div>)}</div>
    </section>
  </div>
}

function Empty({ title }) { return <section className="panel empty-state"><h2>{title}</h2><p>This view will populate as transactions and customer activity arrive.</p></section> }

export default function App() {
  const claimToken = window.location.pathname.match(/^\/claim\/([^/]+)$/)?.[1]
  const customerSlug = window.location.pathname.match(/^\/customer\/([^/]+)\/?$/)?.[1]
  const isAdmin = /^\/admin\/?$/.test(window.location.pathname)
  const [user, setUser] = useState(undefined)
  useEffect(() => { api.get('/me').then(r => setUser(r.data)).catch(() => setUser(null)) }, [])
  if (claimToken) return <ClaimPage token={claimToken}/>
  if (customerSlug) return <CustomerPortal slug={customerSlug}/>
  if (user === undefined) return <div className="loading full">Loading...</div>
  if (isAdmin) return <AdminPortal user={user} setUser={setUser}/>
  if (!user) return <Login onLogin={setUser} />
  if (user.role === 'super_admin') {
    window.location.replace('/admin')
    return null
  }
  const logout = async () => { await api.post('/logout'); setUser(null) }
  return <Shell user={user} onLogout={logout}/>
}
