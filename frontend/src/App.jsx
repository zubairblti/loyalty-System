import { Suspense, useCallback, useEffect, useRef, useState } from 'react'
import { CardCapture, PayerAuthentication } from '@sfpy/atoms'
import '@sfpy/atoms/styles'
import { QRCodeSVG } from 'qrcode.react'
import {
  Building2, CreditCard, Globe2, LayoutDashboard, LogOut, MonitorSmartphone,
  Activity, ArrowLeft, BadgeCheck, Bell, CircleUserRound, Crown, Gem, Medal, Plus, QrCode, ReceiptText, Search, Settings2, ShieldCheck, Star, Trash2, UserPlus, Users, WalletCards, X,
} from 'lucide-react'
import { api, login, prepareCsrf } from './api'
import { connectNotifications, connectRealtime } from './realtime'
import { formatPhone } from './phone'
import { toast } from './toast'
import { Pagination, PasswordInput, PhoneInput, SecurityForm } from './components/Common'
import { useDismissable, useFocusTrap } from './hooks'
import { contrastText } from './design'
import './styles.css'

const money = (value) => new Intl.NumberFormat('en-PK', { style: 'currency', currency: 'PKR', maximumFractionDigits: 0 }).format(value || 0)
const defaultBranding = { brand_name: 'LoyaltyOS', brand_primary_color: '#123e63', brand_accent_color: '#16805a', brand_text_color: '#ffffff', logo_url: null }
const tierPresets = {
  Silver: ['Priority Support', 'Birthday Reward', 'Early Access to Promotions'],
  Gold: ['5% Discount', 'Free Delivery', 'Exclusive Offers'],
  Platinum: ['10% Discount', 'Free Premium Services', 'Dedicated Customer Support', 'Exclusive Events'],
  Diamond: ['15% Discount', 'VIP Priority Service', 'Premium Rewards', 'Anniversary Gift'],
  VIP: ['Highest Priority Support', 'Custom Pricing', 'Exclusive Invitations', 'Premium Membership Benefits'],
}
const tierIcons = { Silver: Medal, Gold: Star, Platinum: Crown, Diamond: Gem, VIP: ShieldCheck }
const hashView = fallback => decodeURIComponent(window.location.hash.slice(1)) || fallback

function TierIcon({ name, size = 20 }) {
  const Icon = tierIcons[name] || BadgeCheck
  return <Icon size={size}/>
}

function AuthLoadingScreen({ label = 'Checking your session...' }) {
  return <main className="auth-loading-screen" aria-live="polite" aria-busy="true">
    <div className="auth-loading-mark"><ShieldCheck size={25}/></div>
    <div className="auth-loading-spinner"/>
    <strong>{label}</strong>
  </main>
}
const brandingStyle = branding => ({
  '--business-primary': branding?.brand_primary_color || defaultBranding.brand_primary_color,
  '--business-primary-text': branding?.brand_text_color || defaultBranding.brand_text_color,
  '--business-accent': branding?.brand_accent_color || defaultBranding.brand_accent_color,
  '--business-accent-text': contrastText(branding?.brand_accent_color),
})

function BrandLogo({ branding, small = false }) {
  const name = branding?.brand_name || 'LoyaltyOS'
  return branding?.logo_url
    ? <img className={`brand-logo${small ? ' small' : ''}`} src={branding.logo_url} alt={`${name} logo`}/>
    : <span className={`brand-mark${small ? ' small' : ''}`}>{name.charAt(0).toUpperCase()}</span>
}

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
    try { onLogin(await login(form.email, form.password, form.remember === 'on')) } catch (err) { setError(err.response?.data?.message || 'Login failed') }
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
          <label>Password<PasswordInput name="password" required placeholder="Enter your password"/></label>
          <div className="auth-row"><label className="check-label"><input name="remember" type="checkbox"/>Remember me for 30 days</label><button type="button" className="text-button" onClick={() => switchMode('forgot')}>Forgot password?</button></div>
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
            <label>Mobile number<PhoneInput required/></label>
            <label>Password<PasswordInput name="password" minLength="8" required placeholder="Minimum 8 characters"/></label>
            <label>Confirm password<PasswordInput name="password_confirmation" minLength="8" required placeholder="Repeat password"/></label>
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
          <label>New password<PasswordInput name="password" minLength="8" required/></label>
          <label>Confirm password<PasswordInput name="password_confirmation" minLength="8" required/></label>
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
  const [remember, setRemember] = useState(false)
  const submit = async e => {
    e.preventDefault()
    try {
      const account = await login(email, password, remember)
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
      <label>Password<PasswordInput value={password} onChange={e => setPassword(e.target.value)}/></label>
      <label className="check-label"><input type="checkbox" checked={remember} onChange={e => setRemember(e.target.checked)}/>Remember me for 30 days</label>
      {error && <div className="error">{error}</div>}
      <button className="admin-primary">Sign in to control center</button>
    </form>
  </main>
}

function AdminPortal({ user, setUser }) {
  const [data, setData] = useState(null)
  const [view, setView] = useState(() => hashView('Overview'))
  const [paymentFilters, setPaymentFilters] = useState({ business: '', status: '', from: '', to: '' })
  const [paymentPage, setPaymentPage] = useState(1)
  const [auditSearch, setAuditSearch] = useState('')
  const [auditPage, setAuditPage] = useState(1)
  const [auditFilters, setAuditFilters] = useState({ action: '', business_id: '', actor_id: '', from: '', to: '' })
  const [auditData, setAuditData] = useState({ data: [], current_page: 1, last_page: 1, total: 0, filters: { actions: [], businesses: [], actors: [] } })
  const [selectedBusiness, setSelectedBusiness] = useState(null)
  const [message, setMessage] = useState('')
  const [showBusinessForm, setShowBusinessForm] = useState(false)
  const [selectedPlanId, setSelectedPlanId] = useState(null)
  const [planSearch, setPlanSearch] = useState('')
  const [cashPlanId, setCashPlanId] = useState('')
  const [cashCycle, setCashCycle] = useState('monthly')
  const [businessFilters, setBusinessFilters] = useState({ search: '', status: '', plan: '' })
  const [businessDetail, setBusinessDetail] = useState(null)
  const [businessDirectory, setBusinessDirectory] = useState(null)
  const [notifications, setNotifications] = useState([])
  const [notificationMeta, setNotificationMeta] = useState(null)
  const [notificationPage, setNotificationPage] = useState(1)
  const [unreadCount, setUnreadCount] = useState(0)
  const [showNotifications, setShowNotifications] = useState(false)
  const [promptState, setPromptState] = useState(null)
  const [businessPage, setBusinessPage] = useState(1)
  const load = () => api.get('/admin/dashboard').then(r => {
    setData(r.data)
    setSelectedBusiness(current => current ? r.data.businesses.find(item => item.id === current.id) || null : null)
  })
  const loadNotifications = (page = notificationPage) => api.get('/notifications', { params: { page }, showLoader: false }).then(r => { setNotifications(r.data.data || r.data); setNotificationMeta(r.data.meta || r.data); setUnreadCount(r.data.unread_count || 0) }).catch(() => {})
  const requestReason = (title, required = true) => new Promise(resolve => setPromptState({ title, required, resolve }))
  useDismissable(showNotifications, () => setShowNotifications(false))
  useFocusTrap(Boolean(promptState), '.reason-dialog')
  useEffect(() => {
    if (!promptState) return undefined
    const close = event => { if (event.key === 'Escape') { promptState.resolve(null); setPromptState(null) } }
    document.addEventListener('keydown', close)
    return () => document.removeEventListener('keydown', close)
  }, [promptState])
  useEffect(() => setPaymentPage(1), [paymentFilters])
  useEffect(() => {
    if (user?.role !== 'super_admin' || view !== 'Activity') return undefined
    const timer = window.setTimeout(() => api.get('/admin/activity', { params: { search: auditSearch || undefined, ...Object.fromEntries(Object.entries(auditFilters).map(([key, value]) => [key, value || undefined])), page: auditPage }, showLoader: false }).then(response => setAuditData(response.data)).catch(() => {}), 250)
    return () => window.clearTimeout(timer)
  }, [user, view, auditSearch, auditFilters, auditPage])
  useEffect(() => { if (user?.role === 'super_admin') load() }, [user])
  useEffect(() => { if (user?.role === 'super_admin') loadNotifications() }, [user])
  useEffect(() => user?.id ? connectNotifications(user.id, loadNotifications) : undefined, [user?.id])
  useEffect(() => {
    if (user?.role !== 'super_admin') return undefined
    const timer = window.setTimeout(() => {
      api.get('/admin/businesses', { params: { search: businessFilters.search || undefined, status: businessFilters.status || undefined, plan_id: businessFilters.plan || undefined, page: businessPage }, showLoader: false })
        .then(r => setBusinessDirectory(r.data))
        .catch(() => {})
    }, 250)
    return () => window.clearTimeout(timer)
  }, [user, businessFilters, businessPage])
  if (!user) return <AdminLogin onLogin={setUser}/>
  if (user.role !== 'super_admin') return <AdminLogin onLogin={setUser}/>
  if (!data) return <div className="loading full">Loading platform...</div>

  const updateBusinessStatus = async (business, status) => {
    const reason = status === 'active' ? null : await requestReason(`Reason for ${status}`)
    if (status !== 'active' && !reason) return
    try { await api.patch(`/admin/businesses/${business.id}`, { status, reason }); setMessage(`Business status changed to ${status}.`); await load(); setBusinessFilters(current => ({ ...current })) }
    catch (err) { setMessage(err.response?.data?.message || 'Business status could not be changed.') }
  }
  const logout = async () => { await api.post('/logout'); setUser(null) }
  const reviewPayment = async (payment, status) => {
    const admin_note = await requestReason(status === 'paid' ? 'Verification note' : `Reason for ${status}`, status !== 'paid')
    if (status !== 'paid' && !admin_note) return
    await api.post(`/admin/payments/${payment.id}/review`, { status, admin_note })
    setMessage(`Payment marked ${status}.`); load()
  }
  const recordCash = async (e, business) => {
    e.preventDefault()
    e.currentTarget.dataset.idempotencyKey ||= crypto.randomUUID()
    const form = Object.fromEntries(new FormData(e.currentTarget))
    try { await api.post(`/admin/businesses/${business.id}/cash-payment`, { ...form, idempotency_key: e.currentTarget.dataset.idempotencyKey, plan_id: Number(form.plan_id), amount: form.amount ? Number(form.amount) : null }); delete e.currentTarget.dataset.idempotencyKey; setMessage(`Cash subscription activated for ${business.name}.`); await load(); setBusinessFilters(current => ({ ...current })); setBusinessDetail((await api.get(`/admin/businesses/${business.id}`)).data); return true }
    catch (err) { setMessage(err.response?.data?.message || 'Cash payment could not be recorded.'); return false }
  }
  const createBusiness = async e => {
    e.preventDefault()
    try { await api.post('/admin/businesses', Object.fromEntries(new FormData(e.currentTarget))); setMessage('Business account created.'); setShowBusinessForm(false); await load(); setBusinessFilters(current => ({ ...current })) }
    catch (err) { setMessage(err.response?.data?.message || Object.values(err.response?.data?.errors || {})[0]?.[0] || 'Business could not be created.') }
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
      duration_months: Number(form.duration_months),
      display_order: Number(form.display_order),
      active: form.active === 'on', public: form.public === 'on',
      features: form.features.split('\n').map(value => value.trim()).filter(Boolean),
    }
    try { if (selectedPlanId) await api.put(`/admin/plans/${selectedPlanId}`, payload); else await api.post('/admin/plans', payload); setMessage('Plan saved.'); load() }
    catch (err) { setMessage(err.response?.data?.message || 'Plan could not be saved.') }
  }
  const deletePlan = async plan => {
    try { if (plan.deleted_at) await api.post(`/admin/plans/${plan.id}/restore`); else await api.delete(`/admin/plans/${plan.id}`); setMessage(plan.deleted_at ? 'Plan restored.' : 'Plan archived.'); setSelectedPlanId(null); load() }
    catch (err) { setMessage(err.response?.data?.message || 'Plan status could not be changed.') }
  }
  const openBusiness = async business => {
    setSelectedBusiness(business); setBusinessDetail(null); setView('Business detail')
    try { setBusinessDetail((await api.get(`/admin/businesses/${business.id}`)).data) }
    catch (err) { setMessage(err.response?.data?.message || 'Business details could not be loaded.') }
  }
  const metrics = [
    ['Total businesses', data.metrics.businesses, Building2],
    ['Active subscriptions', data.metrics.active_subscriptions, ShieldCheck],
    ['Pending payments', data.metrics.pending_payments, ReceiptText],
    ['Subscription revenue', money(data.metrics.subscription_revenue), CreditCard],
    ['Active businesses', data.metrics.active_businesses || 0, Activity],
  ]
  const allFilteredPayments = (data.payments || []).filter(payment => {
    const date = payment.created_at?.slice(0, 10)
    return (!paymentFilters.business || String(payment.business_id) === paymentFilters.business)
      && (!paymentFilters.status || payment.status === paymentFilters.status)
      && (!paymentFilters.from || date >= paymentFilters.from)
      && (!paymentFilters.to || date <= paymentFilters.to)
  })
  const filteredPayments = allFilteredPayments.slice((paymentPage - 1) * 50, paymentPage * 50)
  const activePlans = data.plans.filter(plan => plan.active && !plan.deleted_at)
  const visiblePlans = data.plans.filter(plan => plan.name.toLowerCase().includes(planSearch.toLowerCase()))
  const directoryBusinesses = businessDirectory?.data || data.businesses
  const cashPlan = activePlans.find(plan => String(plan.id) === String(cashPlanId)) || activePlans[0]
  const cashMonthlyPrice = Number(cashPlan?.monthly_price || 0)
  const cashAmount = cashPlan ? (cashCycle === 'yearly'
    ? cashMonthlyPrice * 12 * (1 - Number(cashPlan.yearly_discount_percent || 0) / 100)
    : cashMonthlyPrice) : 0
  return <div className="admin-shell">
    <aside className="admin-sidebar">
      <div className="admin-brand"><span className="admin-emblem small"><ShieldCheck size={18}/></span><div><strong>{user.admin_brand_name || 'LoyaltyOS'}</strong><small>{user.admin_brand_subtitle || 'Control center'}</small></div></div>
      <nav><button className={view === 'Overview' ? 'active' : ''} onClick={() => setView('Overview')}><LayoutDashboard size={18}/>Platform overview</button><button className={view === 'Businesses' ? 'active' : ''} onClick={() => setView('Businesses')}><Building2 size={18}/>Businesses</button><button className={view === 'Payments' ? 'active' : ''} onClick={() => setView('Payments')}><ReceiptText size={18}/>Payments</button><button className={view === 'Plans' ? 'active' : ''} onClick={() => setView('Plans')}><CreditCard size={18}/>Plans</button><button className={view === 'Activity' ? 'active' : ''} onClick={() => setView('Activity')}><Activity size={18}/>Activity</button><button className={view === 'Notifications' ? 'active' : ''} onClick={() => setView('Notifications')}><Bell size={18}/>Notifications</button><button className={view === 'Security' ? 'active' : ''} onClick={() => setView('Security')}><ShieldCheck size={18}/>Security</button></nav>
      <div className="admin-account"><div><strong>{user.name}</strong><small>Super Administrator</small></div><button title="Log out" onClick={logout}><LogOut size={18}/></button></div>
    </aside>
    <main className="admin-workspace">
      <header><div>{view !== 'Overview' && <button className="back-button" onClick={() => { setView(view === 'Business detail' ? 'Businesses' : 'Overview'); setBusinessDetail(null) }}><ArrowLeft size={17}/>{view === 'Business detail' ? 'Back to businesses' : 'Back to overview'}</button>}<small className="admin-eyebrow">PLATFORM OPERATIONS</small><h1>{view === 'Business detail' ? businessDetail?.name || selectedBusiness?.name || 'Business details' : view}</h1><p>{view === 'Plans' ? 'Create, publish and manage subscription plans' : view === 'Payments' ? 'Reconciled subscription payments across every business' : 'Tenant health, subscriptions and usage across LoyaltyOS'}</p></div><div className="workspace-actions"><button className="icon-button notification-button" title="Notifications" aria-label={`Notifications, ${unreadCount} unread`} aria-expanded={showNotifications} onClick={() => setShowNotifications(value => !value)}><Bell size={19}/>{unreadCount > 0 && <span className="notification-count">{unreadCount > 99 ? '99+' : unreadCount}</span>}</button>{showNotifications && <div className="notification-menu" role="menu"><div><strong>Notifications</strong><button onClick={async () => { await api.post('/notifications/read', {}, { showLoader: false }); loadNotifications() }}>Mark all read</button></div>{notifications.slice(0, 6).map(item => <button role="menuitem" className={`notification-entry${!item.read_at ? ' unread' : ''}`} key={item.id} onClick={async () => { if (!item.read_at) await api.post(`/notifications/${item.id}/read`, {}, { showLoader: false }); setShowNotifications(false); if (item.data.action_url) window.location.assign(item.data.action_url); else setView('Notifications'); loadNotifications() }}><strong>{item.data.title}</strong><p>{item.data.message}</p><small>{new Date(item.created_at).toLocaleString()}</small></button>)}<button className="notification-view-all" onClick={() => { setView('Notifications'); setShowNotifications(false) }}>View all notifications</button></div>}{view === 'Businesses' && <button className="admin-primary" onClick={() => setShowBusinessForm(value => !value)}><UserPlus size={17}/>Add business</button>}</div></header>
      {message && <div className="notice admin-notice">{message}</div>}
      {view === 'Overview' && <><section className="admin-metrics">{metrics.map(([label, value, Icon]) => <article key={label}><div><small>{label}</small><strong>{value}</strong></div><span><Icon size={19}/></span></article>)}</section><AdminOverviewCharts charts={data.charts}/>
      <div className="admin-columns">
        <section className="panel"><div className="panel-head"><div><h2>Businesses</h2><p>Recent tenant accounts and platform usage</p></div></div>
          <table><thead><tr><th>Business</th><th>Plan</th><th>Customers</th><th>Orders</th><th>Status</th><th></th></tr></thead>
            <tbody>{data.businesses.map(b => <tr key={b.id}><td><strong>{b.name}</strong><small className="table-sub">/{b.slug}</small></td><td>{b.plan?.name || 'Unsubscribed'}</td><td>{b.customers_count}</td><td>{b.orders_count}</td><td><span className={b.status === 'active' ? 'status' : 'status disabled'}>{b.status}</span></td><td className="right"><button className="table-action" onClick={() => openBusiness(b)}>View</button></td></tr>)}</tbody></table>
        </section>
        <section className="panel plan-panel"><div className="panel-head"><div><h2>Plans</h2><p>Current tenant distribution</p></div></div>
          <div className="plan-list">{data.plans.map(plan => <div key={plan.id}><span><CreditCard size={17}/></span><div><strong>{plan.name}</strong><small>{plan.domain_limit} domains · {plan.terminal_limit} terminals</small></div><b>{plan.businesses_count}</b></div>)}</div>
        </section>
      </div></>}
      {view === 'Businesses' && <div className="business-admin-grid">
        {showBusinessForm && <form className="panel admin-create-business" onSubmit={createBusiness}><div className="panel-head"><div><h2>Create business</h2><p>Uses the same fields as self-registration</p></div></div><div className="plan-form-grid">
          <label>Business name<input name="business_name" required/></label><label>Full name<input name="name" required/></label><label>Email address<input name="email" type="email" required/></label><label>Mobile number<PhoneInput required/></label><label>Password<PasswordInput name="password" minLength="8" required/></label><label>Confirm password<PasswordInput name="password_confirmation" minLength="8" required/></label><button className="admin-primary plan-save">Create pending business</button>
        </div></form>}
        <section className="panel business-directory"><div className="panel-head"><div><h2>All businesses</h2><p>Self-registered and administrator-created accounts</p></div></div><div className="business-filters"><label className="search-field"><Search size={16}/><input value={businessFilters.search} onChange={e => { setBusinessFilters({ ...businessFilters, search: e.target.value }); setBusinessPage(1) }} placeholder="Search name, owner, email or phone"/></label><select value={businessFilters.status} onChange={e => { setBusinessFilters({ ...businessFilters, status: e.target.value }); setBusinessPage(1) }}><option value="">All statuses</option><option value="pending">Pending</option><option value="active">Active</option><option value="suspended">Suspended</option><option value="expired">Expired</option><option value="rejected">Rejected</option></select><select value={businessFilters.plan} onChange={e => { setBusinessFilters({ ...businessFilters, plan: e.target.value }); setBusinessPage(1) }}><option value="">All plans</option>{data.plans.map(plan => <option key={plan.id} value={plan.id}>{plan.name}</option>)}</select></div>
          <div className="tenant-list">{directoryBusinesses.map(b => <button key={b.id} onClick={() => openBusiness(b)}><span>{b.name[0]}</span><div><strong>{b.name}</strong><small>{b.owner?.email || b.owner?.phone || 'Owner unavailable'} · {b.plan?.name || 'Subscription required'}</small></div><b>{b.status}</b></button>)}
            {!directoryBusinesses.length && <div className="customer-empty">No businesses match these filters.</div>}</div>
          {businessDirectory?.last_page > 1 && <div className="directory-pagination"><button className="secondary" disabled={businessPage <= 1} onClick={() => setBusinessPage(page => page - 1)}><ArrowLeft size={15}/>Previous</button><span>Page {businessDirectory.current_page} of {businessDirectory.last_page}</span><button className="secondary" disabled={businessPage >= businessDirectory.last_page} onClick={() => setBusinessPage(page => page + 1)}>Next</button></div>}
        </section>
        <section className="panel pending-review"><div className="panel-head"><div><h2>Pending approvals</h2><p>Verify payment ID and screenshot before approval</p></div></div>
          <div className="review-list">{data.pending_payments.map(payment => <div key={payment.id}><div><strong>{payment.business.name}</strong><small>{payment.method} · {payment.transaction_reference || `Card •••• ${payment.card_last_four}`} · {money(payment.amount)}</small></div><div><button className="table-action reject" onClick={() => reviewPayment(payment, 'failed')}>Reject</button><button className="primary small-action" onClick={() => reviewPayment(payment, 'paid')}>Verify paid</button></div></div>)}
            {!data.pending_payments.length && <div className="customer-empty">No payments waiting for review.</div>}</div>
        </section>
      </div>}
      {view === 'Business detail' && <AdminBusinessDetail business={businessDetail} activePlans={activePlans} cashPlan={cashPlan} cashCycle={cashCycle} cashAmount={cashAmount} onCashPlan={setCashPlanId} onCashCycle={setCashCycle} onCash={recordCash} onStatus={updateBusinessStatus}/>}
      {view === 'Payments' && <div className="admin-payments">
        <section className="payment-metrics">
          <article><small>PAID REVENUE</small><strong>{money(data.payment_metrics?.paid_total)}</strong></article>
          <article><small>SAFEPAY CARD REVENUE</small><strong>{money(data.payment_metrics?.card_total)}</strong></article>
          <article><small>SUCCESSFUL PAYMENTS</small><strong>{data.payment_metrics?.paid_count || 0}</strong></article>
          <article><small>PROCESSING VALUE</small><strong>{money(data.payment_metrics?.processing_total)}</strong></article>
        </section>
        <section className="panel payments-ledger">
          <div className="panel-head"><div><h2>Payment ledger</h2><p>Safepay, cash and manually reviewed subscription payments</p></div><span className="status">{filteredPayments.length} records</span></div>
          <div className="payment-filters">
            <label>Business<select value={paymentFilters.business} onChange={e => setPaymentFilters({ ...paymentFilters, business: e.target.value })}><option value="">All businesses</option>{data.businesses.map(b => <option key={b.id} value={b.id}>{b.name}</option>)}</select></label>
            <label>Status<select value={paymentFilters.status} onChange={e => setPaymentFilters({ ...paymentFilters, status: e.target.value })}><option value="">All statuses</option><option value="paid">Paid</option><option value="processing">Processing</option><option value="pending">Pending</option><option value="failed">Failed</option><option value="refunded">Refunded</option></select></label>
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
              <td><span className={`status ${['failed', 'refunded'].includes(payment.status) ? 'disabled' : ['processing', 'pending'].includes(payment.status) ? 'pending' : ''}`}>{payment.status}</span></td>
            </tr>)}
            {!filteredPayments.length && <tr><td colSpan="7" className="customer-empty">No payments match these filters.</td></tr>}</tbody>
          </table>
          <Pagination meta={{ last_page: Math.max(1, Math.ceil(allFilteredPayments.length / 50)) }} page={paymentPage} onPage={setPaymentPage}/>
        </section>
      </div>}
      {view === 'Plans' && <div className="plan-management">
        <section className="panel plan-catalog"><div className="panel-head"><div><h2>Subscription plans</h2><p>Published plans appear in business checkout</p></div><button className="admin-primary" onClick={() => setSelectedPlanId(null)}><Plus size={17}/>New plan</button></div><div className="table-toolbar"><label className="search-field"><Search size={16}/><input value={planSearch} onChange={event => setPlanSearch(event.target.value)} placeholder="Search plans" aria-label="Search plans"/></label></div>
          <div className="plan-list">{visiblePlans.map(plan => <button className={selectedPlanId === plan.id ? 'selected' : ''} key={plan.id} onClick={() => setSelectedPlanId(plan.id)}><span><CreditCard size={17}/></span><div><strong>{plan.name}</strong><small>{money(plan.monthly_price)}/month · {plan.businesses_count} businesses</small></div><b>{plan.deleted_at ? 'Archived' : plan.active && plan.public ? 'Published' : 'Hidden'}</b></button>)}{!visiblePlans.length && <div className="customer-empty">No plans match your search.</div>}</div>
        </section>
        {(() => { const plan = data.plans.find(item => item.id === selectedPlanId) || {}; return <form key={plan.id || 'new'} className="panel" onSubmit={savePlan}>
          <div className="panel-head"><div><h2>{plan.id ? 'Edit plan' : 'Create plan'}</h2><p>Pricing, access limits and checkout visibility</p></div>{plan.id && <button type="button" title={plan.deleted_at ? 'Restore plan' : 'Archive plan'} className="icon-button" onClick={() => deletePlan(plan)}><Trash2 size={17}/></button>}</div>
          <div className="plan-form-grid"><label>Plan name<input name="name" required defaultValue={plan.name || ''}/></label><label>Monthly price (PKR)<input name="monthly_price" type="number" min="0" required defaultValue={plan.monthly_price ?? 5000}/></label><label className="wide">Description<textarea name="description" rows="2" defaultValue={plan.description || ''}/></label><label>Yearly discount (%)<input name="yearly_discount_percent" type="number" min="0" max="90" required defaultValue={plan.yearly_discount_percent ?? 30}/></label><label>Duration (months)<input name="duration_months" type="number" min="1" required defaultValue={plan.duration_months ?? 1}/></label><label>Checkout position <small>0 appears first</small><input name="display_order" type="number" min="0" required defaultValue={plan.display_order ?? 0}/></label><label>Verified domains<input name="domain_limit" type="number" min="0" required defaultValue={plan.domain_limit ?? 1}/></label><label>POS terminals<input name="terminal_limit" type="number" min="0" required defaultValue={plan.terminal_limit ?? 1}/></label><label>QR codes<input name="qr_limit" type="number" min="0" required defaultValue={plan.qr_limit ?? 10}/></label><label>Monthly orders<input name="monthly_order_limit" type="number" min="1" required defaultValue={plan.monthly_order_limit ?? 1000}/></label><label className="check-label"><input name="active" type="checkbox" defaultChecked={plan.active ?? true}/>Active</label><label className="check-label"><input name="public" type="checkbox" defaultChecked={plan.public ?? true}/>Public checkout</label><label className="features-field">Features, one per line<textarea name="features" rows="7" required defaultValue={(plan.features || ['Points ledger and customer rewards']).join('\n')}/></label><button className="admin-primary plan-save" disabled={Boolean(plan.deleted_at)}>Save plan</button></div>
        </form> })()}
      </div>}
      {view === 'Activity' && <AdminAuditActivity audit={auditData} search={auditSearch} filters={auditFilters} onSearch={value => { setAuditSearch(value); setAuditPage(1) }} onFilters={value => { setAuditFilters(value); setAuditPage(1) }} page={auditPage} onPage={setAuditPage}/>}
      {view === 'Notifications' && <section className="panel notification-page"><div className="panel-head"><div><h2>All notifications</h2><p>Platform and subscription activity</p></div><button className="secondary" onClick={async () => { await api.post('/notifications/read', {}, { showLoader: false }); loadNotifications() }}>Mark all as read</button></div><div>{notifications.map(item => <button key={item.id} className={`notification-entry${!item.read_at ? ' unread' : ''}`} onClick={async () => { if (!item.read_at) await api.post(`/notifications/${item.id}/read`, {}, { showLoader: false }); if (item.data.action_url) window.location.assign(item.data.action_url); loadNotifications() }}><span><Bell size={18}/></span><div><strong>{item.data.title}</strong><p>{item.data.message}</p><small>{new Date(item.created_at).toLocaleString()}</small></div></button>)}{!notifications.length && <div className="customer-empty">No notifications yet.</div>}</div><Pagination meta={notificationMeta} page={notificationPage} onPage={page => { setNotificationPage(page); loadNotifications(page) }}/></section>}
      {view === 'Security' && <div className="settings-stack"><AdminProfileForm user={user} onUpdated={setUser}/><SecurityForm /></div>}
      {promptState && <div className="admin-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="reason-title"><form className="admin-modal reason-dialog" onSubmit={event => { event.preventDefault(); const value = new FormData(event.currentTarget).get('reason')?.trim() || null; promptState.resolve(value); setPromptState(null) }}><div className="panel-head"><div><h2 id="reason-title">{promptState.title}</h2><p>{promptState.required ? 'A reason is required for the audit history.' : 'Add an optional note for the audit history.'}</p></div><button type="button" className="icon-button" onClick={() => { promptState.resolve(null); setPromptState(null) }}><X size={17}/></button></div><div className="reason-dialog-body"><label>Note<textarea name="reason" rows="4" required={promptState.required} autoFocus/></label><div><button type="button" className="secondary" onClick={() => { promptState.resolve(null); setPromptState(null) }}>Cancel</button><button className="admin-primary">Continue</button></div></div></form></div>}
    </main>
  </div>
}

function AdminProfileForm({ user, onUpdated }) {
  const [feedback, setFeedback] = useState(null)
  const submit = async event => {
    event.preventDefault(); setFeedback(null)
    try {
      const { data } = await api.put('/admin/profile', Object.fromEntries(new FormData(event.currentTarget)))
      onUpdated(data); setFeedback({ type: 'notice', message: 'Admin profile and control-center branding updated.' })
    } catch (error) {
      setFeedback({ type: 'error', message: Object.values(error.response?.data?.errors || {})[0]?.[0] || error.response?.data?.message || 'Profile could not be updated.' })
    }
  }
  return <form className="panel" onSubmit={submit}><div className="panel-head"><div><h2>Admin profile and branding</h2><p>Update your identity, login email and control-center name</p></div><Settings2 size={20}/></div><div className="admin-profile-fields"><label>Admin name<input name="name" required defaultValue={user.name}/></label><label>Login email<input name="email" type="email" required defaultValue={user.email}/></label><label>Brand name<input name="admin_brand_name" required defaultValue={user.admin_brand_name || 'LoyaltyOS'}/></label><label>Brand subtitle<input name="admin_brand_subtitle" required defaultValue={user.admin_brand_subtitle || 'Control center'}/></label><label className="wide">Current password <small>Only required when changing email</small><PasswordInput name="current_password" autoComplete="current-password"/></label>{feedback && <div className={`${feedback.type} wide`}>{feedback.message}</div>}<button className="primary wide">Save profile and branding</button></div></form>
}

function AdminAuditActivity({ audit, search, filters, onSearch, onFilters, page, onPage }) {
  const options = audit.filters || { actions: [], businesses: [], actors: [] }
  const update = (key, value) => onFilters({ ...filters, [key]: value })
  return <section className="panel payments-ledger"><div className="panel-head"><div><h2>Admin activity</h2><p>Actions performed by Super Administrators only</p></div><span className="status">{audit.total || 0} events</span></div><div className="audit-filters"><label className="search-field"><Search size={16}/><input value={search} onChange={event => onSearch(event.target.value)} placeholder="Search action, business or admin"/></label><label>Action<select value={filters.action} onChange={event => update('action', event.target.value)}><option value="">All actions</option>{options.actions.map(action => <option key={action} value={action}>{action.replaceAll('.', ' ')}</option>)}</select></label><label>Business<select value={filters.business_id} onChange={event => update('business_id', event.target.value)}><option value="">All businesses</option>{options.businesses.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><label>Admin<select value={filters.actor_id} onChange={event => update('actor_id', event.target.value)}><option value="">All admins</option>{options.actors.map(item => <option key={item.id} value={item.id}>{item.name}</option>)}</select></label><label>From<input type="date" value={filters.from} onChange={event => update('from', event.target.value)}/></label><label>To<input type="date" value={filters.to} onChange={event => update('to', event.target.value)}/></label><button className="secondary" onClick={() => onFilters({ action: '', business_id: '', actor_id: '', from: '', to: '' })}>Clear</button></div><table><thead><tr><th>Action</th><th>Business</th><th>Performed by</th><th>Date</th></tr></thead><tbody>{audit.data.map(log => <tr key={log.id}><td><strong>{log.action.replaceAll('.', ' ')}</strong></td><td>{log.business?.name || 'Platform'}</td><td>{log.actor?.name || 'Administrator'}</td><td>{new Date(log.created_at).toLocaleString()}</td></tr>)}{!audit.data.length && <tr><td colSpan="4" className="empty-row">No admin activity matches these filters.</td></tr>}</tbody></table><Pagination meta={audit} page={page} onPage={onPage}/></section>
}

function AdminOverviewCharts({ charts }) {
  const monthly = charts?.monthly || []
  const plans = charts?.plans || []
  const maxBusiness = Math.max(1, ...monthly.map(item => item.businesses))
  const maxRevenue = Math.max(1, ...monthly.map(item => item.revenue))
  const maxPlans = Math.max(1, ...plans.map(item => item.businesses))
  return <section className="overview-charts"><article className="panel"><div className="panel-head"><div><h2>Business growth</h2><p>New businesses during the last six months</p></div></div><div className="bar-chart">{monthly.map(item => <div key={item.label}><span style={{ height: `${Math.max(5, item.businesses / maxBusiness * 100)}%` }}/><strong>{item.businesses}</strong><small>{item.label}</small></div>)}</div></article><article className="panel"><div className="panel-head"><div><h2>Subscription revenue</h2><p>Paid subscription revenue by month</p></div></div><div className="bar-chart revenue-chart">{monthly.map(item => <div key={item.label}><span style={{ height: `${Math.max(5, item.revenue / maxRevenue * 100)}%` }}/><strong>{money(item.revenue)}</strong><small>{item.label}</small></div>)}</div></article><article className="panel plan-distribution"><div className="panel-head"><div><h2>Active businesses by plan</h2><p>Current subscription distribution</p></div></div><div>{plans.map(item => <div key={item.name}><label><span>{item.name}</span><strong>{item.businesses}</strong></label><i><b style={{ width: `${item.businesses / maxPlans * 100}%` }}/></i></div>)}</div></article></section>
}

function BusinessAuditHistory({ businessId }) {
  const [filters, setFilters] = useState({ search: '', action: '', actor_id: '', from: '', to: '' })
  const [page, setPage] = useState(1)
  const [audit, setAudit] = useState({ data: [], last_page: 1, total: 0, filters: { actions: [], actors: [] } })
  useEffect(() => {
    const timer = window.setTimeout(() => api.get(`/admin/businesses/${businessId}/activity`, { params: { ...Object.fromEntries(Object.entries(filters).map(([key, value]) => [key, value || undefined])), page }, showLoader: false }).then(response => setAudit(response.data)).catch(() => {}), 200)
    return () => window.clearTimeout(timer)
  }, [businessId, filters, page])
  const update = (key, value) => { setFilters(current => ({ ...current, [key]: value })); setPage(1) }
  return <section className="panel full-detail-table"><div className="panel-head"><div><h2>Audit history</h2><p>Business-specific administrative, payment and onboarding changes</p></div><span className="status">{audit.total || 0} events</span></div><div className="audit-filters business-audit-filters"><label className="search-field"><Search size={16}/><input value={filters.search} onChange={event => update('search', event.target.value)} placeholder="Search audit history"/></label><label>Action<select value={filters.action} onChange={event => update('action', event.target.value)}><option value="">All actions</option>{(audit.filters?.actions || []).map(action => <option key={action} value={action}>{action.replaceAll('.', ' ')}</option>)}</select></label><label>Actor<select value={filters.actor_id} onChange={event => update('actor_id', event.target.value)}><option value="">All actors</option>{(audit.filters?.actors || []).map(actor => <option key={actor.id} value={actor.id}>{actor.name}</option>)}</select></label><label>From<input type="date" value={filters.from} onChange={event => update('from', event.target.value)}/></label><label>To<input type="date" value={filters.to} onChange={event => update('to', event.target.value)}/></label><button className="secondary" onClick={() => { setFilters({ search: '', action: '', actor_id: '', from: '', to: '' }); setPage(1) }}>Clear</button></div><table><thead><tr><th>Action</th><th>Performed by</th><th>Date</th></tr></thead><tbody>{audit.data.map(log => <tr key={log.id}><td>{log.action.replaceAll('.', ' ')}</td><td>{log.actor?.name || 'System / gateway'}</td><td>{new Date(log.created_at).toLocaleString()}</td></tr>)}{!audit.data.length && <tr><td colSpan="3" className="customer-empty">No audit entries match these filters.</td></tr>}</tbody></table><Pagination meta={audit} page={page} onPage={setPage}/></section>
}

function AdminBusinessDetail({ business, activePlans, cashPlan, cashCycle, cashAmount, onCashPlan, onCashCycle, onCash, onStatus }) {
  const [historyMethod, setHistoryMethod] = useState('')
  const [historyStatus, setHistoryStatus] = useState('')
  const [showAllPayments, setShowAllPayments] = useState(false)
  const [showCashPayment, setShowCashPayment] = useState(false)
  if (!business) return <div className="loading">Loading business details...</div>
  const filteredPaymentHistory = (business.payments || []).filter(payment =>
    (!historyMethod || (historyMethod === 'online' ? payment.method !== 'cash' : payment.method === historyMethod))
    && (!historyStatus || payment.status === historyStatus))
  const visiblePaymentHistory = showAllPayments ? filteredPaymentHistory : filteredPaymentHistory.slice(0, 20)
  return <div className="admin-business-detail">
    <section className="panel detail-summary"><div className="panel-head"><div><h2>Registration and profile</h2><p>Account identity, contact and onboarding state</p></div><div className="detail-head-actions"><button className="secondary" onClick={() => setShowCashPayment(true)}><Plus size={16}/>Record cash payment</button><select value={business.status || 'pending'} onChange={e => onStatus(business, e.target.value)}><option value="pending">Pending</option><option value="active">Active</option><option value="suspended">Suspended</option><option value="expired">Expired</option><option value="rejected">Rejected</option></select></div></div><div className="business-facts detail-facts"><div><small>Business</small><strong>{business.name}</strong></div><div><small>Owner</small><strong>{business.owner?.name || 'Not available'}</strong></div><div><small>Email</small><strong>{business.owner?.email || 'Not available'}</strong></div><div><small>Phone</small><strong>{business.owner?.phone || 'Not available'}</strong></div><div><small>Email status</small><strong>{business.owner?.email_verified_at ? 'Verified' : 'Pending'}</strong></div><div><small>Profile</small><strong>{business.profile_completed ? 'Complete' : 'Incomplete'}</strong></div><div><small>Category</small><strong>{business.category || 'Not provided'}</strong></div><div><small>Address</small><strong>{[business.address, business.city, business.country].filter(Boolean).join(', ') || 'Not provided'}</strong></div><div><small>Customer portal</small><strong>/customer/{business.slug}</strong></div><div><small>Created</small><strong>{new Date(business.created_at).toLocaleString()}</strong></div></div></section>
    <section className="panel full-detail-table"><div className="panel-head"><div><h2>Subscription history</h2><p>Current, replaced, expired and cancelled plans</p></div></div><table><thead><tr><th>Plan</th><th>Billing</th><th>Amount</th><th>Starts</th><th>Expires</th><th>Status</th></tr></thead><tbody>{(business.subscriptions || []).map(subscription => <tr key={subscription.id}><td>{subscription.plan?.name || 'Archived plan'}</td><td className="capitalize">{subscription.billing_cycle}</td><td>{money(subscription.amount_paid)}</td><td>{new Date(subscription.starts_at).toLocaleDateString()}</td><td>{new Date(subscription.ends_at).toLocaleDateString()}</td><td><span className="status">{subscription.status}</span></td></tr>)}{!business.subscriptions?.length && <tr><td colSpan="6" className="customer-empty">No subscriptions.</td></tr>}</tbody></table></section>
    <section className="panel full-detail-table"><div className="panel-head"><div><h2>Payment history</h2><p>Online gateway and verified cash payments</p></div><span className="status">{filteredPaymentHistory.length} records</span></div><div className="detail-payment-filters"><label>Method<select value={historyMethod} onChange={e => { setHistoryMethod(e.target.value); setShowAllPayments(false) }}><option value="">All methods</option><option value="online">Online</option><option value="cash">Cash</option></select></label><label>Status<select value={historyStatus} onChange={e => { setHistoryStatus(e.target.value); setShowAllPayments(false) }}><option value="">All statuses</option><option value="paid">Paid</option><option value="processing">Processing</option><option value="pending">Pending</option><option value="failed">Failed</option><option value="refunded">Refunded</option></select></label></div><table><thead><tr><th>Date</th><th>Plan</th><th>Method</th><th>Billing</th><th>Amount</th><th>Gateway reference</th><th>Status</th></tr></thead><tbody>{visiblePaymentHistory.map(payment => <tr key={payment.id}><td>{new Date(payment.payment_date || payment.created_at).toLocaleDateString()}</td><td>{payment.plan?.name || 'Archived plan'}</td><td className="capitalize">{payment.method === 'card' ? 'Safepay card' : payment.method}</td><td className="capitalize">{payment.billing_cycle}</td><td>{money(payment.amount)}</td><td>{payment.method === 'cash' ? '—' : payment.transaction_reference || 'Not available'}</td><td><span className={`status ${['failed', 'refunded'].includes(payment.status) ? 'disabled' : ['pending', 'processing'].includes(payment.status) ? 'pending' : ''}`}>{payment.status}</span></td></tr>)}{!visiblePaymentHistory.length && <tr><td colSpan="7" className="customer-empty">No payments match these filters.</td></tr>}</tbody></table>{filteredPaymentHistory.length > 20 && <div className="history-more"><button className="secondary" onClick={() => setShowAllPayments(value => !value)}>{showAllPayments ? 'Show latest 20' : `Show all ${filteredPaymentHistory.length}`}</button></div>}</section>
    <BusinessAuditHistory businessId={business.id}/>
    {showCashPayment && <div className="admin-modal-backdrop" onMouseDown={event => { if (event.target === event.currentTarget) setShowCashPayment(false) }}><section className="admin-modal"><div className="panel-head"><div><h2>Record verified cash payment</h2><p>Payment and activation are saved atomically</p></div><button className="icon-button" title="Close" onClick={() => setShowCashPayment(false)}><X size={18}/></button></div><form className="cash-payment-form" onSubmit={async e => { if (await onCash(e, business)) setShowCashPayment(false) }}><label>Plan<select name="plan_id" required value={cashPlan?.id || ''} onChange={e => onCashPlan(e.target.value)}>{activePlans.map(plan => <option value={plan.id} key={plan.id}>{plan.name}</option>)}</select></label><label>Billing<select name="billing_cycle" value={cashCycle} onChange={e => onCashCycle(e.target.value)}><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select></label><label>Amount<input name="amount" type="number" value={cashAmount.toFixed(2)} readOnly/></label><label>Payment date<input name="payment_date" type="date" required defaultValue={new Date().toISOString().slice(0, 10)}/></label><label>Activation reason<input name="activation_reason" placeholder="Cash received and verified"/></label><label className="wide">Internal admin note<textarea name="admin_note" rows="2" placeholder="Visible only in the admin platform"/></label><button className="primary wide">Record payment and activate</button></form></section></div>}
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
          <label>Phone number<PhoneInput required/></label>
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
  const [authChecking, setAuthChecking] = useState(true)
  const [authMode, setAuthMode] = useState('login')
  const [pendingEmail, setPendingEmail] = useState('')
  const [seconds, setSeconds] = useState(0)
  const [code, setCode] = useState('')
  const [error, setError] = useState('')
  const [portalView, setPortalView] = useState(() => hashView('dashboard'))
  const [profileFeedback, setProfileFeedback] = useState(null)
  const [customerNotifications, setCustomerNotifications] = useState([])
  const [notificationMeta, setNotificationMeta] = useState(null)
  const [notificationPage, setNotificationPage] = useState(1)
  const [unreadCount, setUnreadCount] = useState(0)
  const [showNotifications, setShowNotifications] = useState(false)
  const reloadDashboard = () => api.get(`/customer/${slug}/dashboard`).then(r => setDashboard(r.data))
  const loadCustomerNotifications = useCallback((page = notificationPage) => api.get(`/customer/${slug}/notifications`, { params: { page }, showLoader: false }).then(r => { setCustomerNotifications(r.data.data || r.data); setNotificationMeta(r.data.meta || r.data); setUnreadCount(r.data.unread_count || 0) }).catch(() => {}), [slug, notificationPage])
  useDismissable(showNotifications, () => setShowNotifications(false))

  useEffect(() => {
    let active = true
    Promise.allSettled([
      api.get(`/customer/${slug}/business`, { showLoader: false }),
      api.get(`/customer/${slug}/dashboard`, { showLoader: false }),
    ]).then(([businessResult, dashboardResult]) => {
      if (!active) return
      if (businessResult.status === 'fulfilled') setBusiness(businessResult.value.data)
      else setError('Business portal not found.')
      if (dashboardResult.status === 'fulfilled') setDashboard(dashboardResult.value.data)
      setAuthChecking(false)
    })
    return () => { active = false }
  }, [slug])

  useEffect(() => {
    if (!seconds) return undefined
    const timer = window.setInterval(() => setSeconds(value => Math.max(0, value - 1)), 1000)
    return () => window.clearInterval(timer)
  }, [seconds])
  useEffect(() => {
    if (!dashboard) return undefined
    loadCustomerNotifications()
    const disconnect = connectNotifications(dashboard.customer.id, loadCustomerNotifications, 'customer', `/api/customer/${slug}/broadcasting/auth`)
    const timer = window.setInterval(loadCustomerNotifications, 30000)
    return () => { disconnect(); window.clearInterval(timer) }
  }, [dashboard, slug, loadCustomerNotifications])

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
    form.remember = form.remember === 'on'
    try {
      await prepareCsrf()
      const { data } = await api.post(`/customer/${slug}/login`, form)
      setDashboard(data)
    } catch (err) { setError(err.response?.data?.message || 'Login failed.') }
  }
  const forgotCustomerPassword = async e => {
    e.preventDefault(); setError('')
    const email = new FormData(e.currentTarget).get('email')
    try { const { data } = await api.post(`/customer/${slug}/forgot-password`, { email }); setPendingEmail(email); beginExpiry(data.expires_at); setAuthMode('reset') }
    catch (err) { setError(err.response?.data?.message || 'Password reset code could not be sent.') }
  }
  const resetCustomerPassword = async e => {
    e.preventDefault(); setError('')
    try { await api.post(`/customer/${slug}/reset-password`, { ...Object.fromEntries(new FormData(e.currentTarget)), email: pendingEmail }); setAuthMode('login'); toast('Password updated. You can sign in now.', 'success') }
    catch (err) { setError(err.response?.data?.message || Object.values(err.response?.data?.errors || {})[0]?.[0] || 'Password reset failed.') }
  }
  const logout = async () => { await api.post(`/customer/${slug}/logout`); setDashboard(null); setAuthMode('login'); setCode('') }
  const updateProfile = async e => {
    e.preventDefault()
    try { await api.patch(`/customer/${slug}/profile`, Object.fromEntries(new FormData(e.currentTarget))); setProfileFeedback({ type: 'success', message: 'Profile updated.' }); reloadDashboard() }
    catch (err) { setProfileFeedback({ type: 'error', message: err.response?.data?.message || 'Profile update failed.' }) }
  }
  if (authChecking) return <AuthLoadingScreen label="Loading your rewards..."/>
  if (!business && !error) return <AuthLoadingScreen label="Loading your rewards..."/>
  if (!dashboard) return <main className="customer-login" style={brandingStyle(business)}>
    <section className="customer-login-panel">
      <div className="customer-brand"><BrandLogo branding={business}/><div><small>REWARDS PORTAL</small><h1>{business?.brand_name || business?.name || 'Rewards'}</h1></div></div>
      <div className="login-copy"><h2>{authMode === 'register' ? 'Create your rewards account' : authMode === 'verify' ? 'Verify your email' : authMode === 'forgot' ? 'Reset your password' : authMode === 'reset' ? 'Choose a new password' : 'Welcome back'}</h2><p>{['verify', 'reset'].includes(authMode) ? `Enter the code sent to ${pendingEmail}.` : authMode === 'forgot' ? 'We will email you a secure 2-minute reset code.' : `Access your ${business?.name || ''} points and purchase history.`}</p></div>
      {!['verify', 'forgot', 'reset'].includes(authMode) && <div className="customer-auth-tabs"><button className={authMode === 'login' ? 'active' : ''} onClick={() => switchAuthMode('login')}>Sign in</button><button className={authMode === 'register' ? 'active' : ''} onClick={() => switchAuthMode('register')}>Register</button></div>}
      {authMode === 'login' && <form onSubmit={loginCustomer} className="claim-form">
        <label>Mobile number<PhoneInput required/></label>
        <label>Password<PasswordInput name="password" required placeholder="Your password"/></label>
        <label className="check-label"><input name="remember" type="checkbox"/>Remember me for 30 days</label>
        <button type="button" className="text-button" onClick={() => switchAuthMode('forgot')}>Forgot password?</button>
        {error && <div className="error">{error}</div>}
        <button className="customer-primary">Sign in</button>
      </form>}
      {authMode === 'register' && <form onSubmit={registerCustomer} className="claim-form">
        <label>Full name<input name="name" required placeholder="Your full name"/></label>
        <label>Email address<input name="email" type="email" required placeholder="you@example.com"/></label>
        <label>Mobile number<PhoneInput required/></label>
        <label>Password<PasswordInput name="password" minLength="8" required placeholder="At least 8 characters"/></label>
        <label>Confirm password<PasswordInput name="password_confirmation" minLength="8" required placeholder="Repeat your password"/></label>
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
      {authMode === 'forgot' && <form onSubmit={forgotCustomerPassword} className="claim-form"><label>Email address<input name="email" type="email" required placeholder="you@example.com"/></label>{error && <div className="error">{error}</div>}<button className="customer-primary">Send reset code</button><button type="button" className="text-button" onClick={() => switchAuthMode('login')}>Back to sign in</button></form>}
      {authMode === 'reset' && <form onSubmit={resetCustomerPassword} className="claim-form"><label>Verification code<input name="code" inputMode="numeric" maxLength="6" required/></label><label>New password<PasswordInput name="password" minLength="8" required/></label><label>Confirm password<PasswordInput name="password_confirmation" minLength="8" required/></label><small className="code-expiry">{seconds ? `Expires in ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}` : 'Code expired'}</small>{error && <div className="error">{error}</div>}<button className="customer-primary" disabled={!seconds}>Update password</button><button type="button" className="text-button" onClick={() => switchAuthMode('forgot')}>Request a new code</button></form>}
      <p className="privacy-note">This account is only for rewards issued by {business?.name || 'this business'}.</p>
    </section>
  </main>

  const progress = dashboard.next_tier_at ? Math.min(100, (dashboard.balance / dashboard.next_tier_at) * 100) : 100
  const customerItems = [
    ['dashboard', 'Dashboard', LayoutDashboard],
    ...(dashboard.loyalty.memberships_enabled ? [['membership', 'My Membership', BadgeCheck]] : []),
    ...(dashboard.loyalty.points_enabled ? [['wallet', 'My Wallet', WalletCards]] : []),
    ['transactions', 'Transactions', ReceiptText],
    ...(dashboard.loyalty.enabled ? [['notifications', 'Notifications', Bell]] : []),
    ['profile', 'Profile', CircleUserRound],
  ]
  const PointsActivity = ({ limit }) => <div className="activity-list">{dashboard.transactions.slice(0, limit).map(tx => <div className="activity-row" key={tx.id}><div className={tx.points >= 0 ? 'activity-icon earn' : 'activity-icon spend'}>{tx.points >= 0 ? '+' : '−'}</div><div><strong>{tx.description || tx.type}</strong><small>{new Date(tx.created_at).toLocaleDateString()}{tx.order ? ` · ${money(tx.order.total)} purchase` : ''}</small></div><b className={tx.points >= 0 ? 'positive' : 'negative'}>{tx.points >= 0 ? '+' : ''}{tx.points} pts</b></div>)}{!dashboard.transactions.length && <div className="customer-empty">Your points activity will appear here.</div>}</div>
  const Purchases = ({ limit }) => <div className="activity-list">{dashboard.orders.slice(0, limit).map(order => <div className="activity-row" key={order.id}><div className="activity-icon order"><ReceiptText size={17}/></div><div><strong>{order.external_id}</strong><small>{new Date(order.created_at).toLocaleDateString()} · {order.payment_method || order.source}</small></div><b>{money(order.total)}</b></div>)}{!dashboard.orders.length && <div className="customer-empty">No linked purchases yet.</div>}</div>
  return <main className="customer-portal" style={brandingStyle(dashboard.business)}>
    <aside className="customer-sidebar">
      <div className="customer-sidebar-brand"><BrandLogo branding={dashboard.business}/><div><strong>{dashboard.business.brand_name || dashboard.business.name}</strong><small>MEMBER PORTAL</small></div></div>
      <nav>{customerItems.map(([key, name, Icon]) => <button key={key} className={portalView === key ? 'active' : ''} onClick={() => setPortalView(key)}><Icon size={18}/><span>{name}</span></button>)}</nav>
      <div className="customer-sidebar-account"><span>{(dashboard.customer.name || dashboard.customer.phone).charAt(0).toUpperCase()}</span><div><strong>{dashboard.customer.name || 'Member'}</strong><small>{formatPhone(dashboard.customer.phone)}</small></div><button title="Sign out" onClick={logout}><LogOut size={17}/></button></div>
    </aside>
    <section className="customer-main">
      <header className="customer-dashboard-header"><div><small>CUSTOMER AREA</small><h1>{customerItems.find(item => item[0] === portalView)?.[1] || 'Dashboard'}</h1></div><div className="customer-header-actions">{dashboard.loyalty.memberships_enabled && dashboard.tier && <span className="tier-badge" style={{ background: dashboard.tier_details?.badge_color }}><TierIcon name={dashboard.tier} size={15}/>{dashboard.tier}</span>}<div className="workspace-actions"><button className="customer-mobile-logout notification-button customer-bell" title="Notifications" aria-label={`Notifications, ${unreadCount} unread`} aria-expanded={showNotifications} onClick={() => setShowNotifications(value => !value)}><Bell size={18}/>{unreadCount > 0 && <span className="notification-count">{unreadCount > 99 ? '99+' : unreadCount}</span>}</button>{showNotifications && <div className="notification-menu"><div><strong>Notifications</strong><button onClick={async () => { await api.post(`/customer/${slug}/notifications/read`, {}, { showLoader: false }); loadCustomerNotifications() }}>Mark all read</button></div>{customerNotifications.slice(0, 6).map(item => <button className={`notification-entry${!item.read_at ? ' unread' : ''}`} key={item.id} onClick={async () => { if (!item.read_at) await api.post(`/customer/${slug}/notifications/${item.id}/read`, {}, { showLoader: false }); setPortalView('notifications'); setShowNotifications(false); loadCustomerNotifications() }}><strong>{item.data.title}</strong><p>{item.data.message}</p><small>{new Date(item.created_at).toLocaleString()}</small></button>)}<button className="notification-view-all" onClick={() => { setPortalView('notifications'); setShowNotifications(false) }}>View all notifications</button></div>}</div><button className="customer-mobile-logout" title="Sign out" onClick={logout}><LogOut size={17}/></button></div></header>
      <div className="customer-content">
      {portalView === 'dashboard' && <><div className="customer-welcome"><div><p>Welcome back</p><h2>{dashboard.customer.name || dashboard.customer.phone}</h2></div></div>{dashboard.loyalty.points_enabled && <section className="wallet compact-wallet">
        <small>AVAILABLE POINTS</small><strong>{dashboard.balance.toLocaleString()}</strong><p>Use your points on your next eligible purchase.</p>
        <div className="tier-progress"><div><span>{dashboard.tier}</span><span>{dashboard.next_tier || 'Top tier'}</span></div><progress value={progress} max="100"/>
          <small>{dashboard.next_tier ? `${Math.max(0, dashboard.next_tier_at - dashboard.balance)} points to ${dashboard.next_tier}` : 'You reached the highest tier'}</small></div>
      </section>}
      {dashboard.loyalty.memberships_enabled && dashboard.tier && <section className="customer-section dashboard-membership">
        <div className="dashboard-membership-title"><TierIcon name={dashboard.tier} size={28}/><div><small>CURRENT MEMBERSHIP</small><h2>{dashboard.tier}</h2></div></div>
        <div className="dashboard-membership-benefits">{dashboard.membership_in_grace_period && <strong className="membership-grace">Earn {dashboard.tier_details.required_points.toLocaleString()} points by {new Date(dashboard.membership_grace_expires_at).toLocaleDateString()} to keep {dashboard.tier}.</strong>}{dashboard.tier_details?.benefits?.map(benefit => <span key={benefit}><BadgeCheck size={15}/>{benefit}</span>)}{!dashboard.tier_details?.benefits?.length && <span>No benefits configured.</span>}</div>
      </section>}
      <div className="customer-grid">
        {dashboard.loyalty.points_enabled && <section className="customer-section"><div className="customer-section-head"><h2>Recent points</h2><button className="section-link" onClick={() => setPortalView('wallet')}>View all</button></div>
          <PointsActivity limit={5}/>
        </section>}
        <section className="customer-section"><div className="customer-section-head"><h2>Recent purchases</h2><button className="section-link" onClick={() => setPortalView('transactions')}>View all</button></div>
          <Purchases limit={5}/>
        </section>
      </div></>}
      {portalView === 'membership' && <div className="membership-view"><section className="membership-card"><small>CURRENT MEMBERSHIP</small><TierIcon name={dashboard.tier} size={38}/><strong>{dashboard.tier || 'No level yet'}</strong><p>{dashboard.next_tier ? `${Math.max(0, dashboard.next_tier_at - dashboard.balance)} more points to reach ${dashboard.next_tier}.` : dashboard.tier ? 'You have reached the highest membership tier.' : 'Keep earning points to unlock your first membership level.'}</p></section><div className="customer-page-stack"><section className="customer-section membership-progress"><div className="customer-section-head"><h2>Tier progress</h2><span>{Math.round(progress)}%</span></div><div><div><strong>{dashboard.balance.toLocaleString()} points</strong><span>{dashboard.next_tier_at ? `${dashboard.next_tier_at.toLocaleString()} required` : 'Highest tier'}</span></div><progress value={progress} max="100"/></div></section><section className="customer-section"><div className="customer-section-head"><h2>Membership benefits</h2><span>{dashboard.tier_details?.benefits?.length || 0} benefits</span></div><div className="membership-benefits">{dashboard.tier_details?.benefits?.map(benefit => <p key={benefit}><BadgeCheck size={16}/>{benefit}</p>)}{!dashboard.tier_details?.benefits?.length && <div className="customer-empty">No benefits are configured for your current level.</div>}</div></section></div></div>}
      {portalView === 'wallet' && <div className="customer-page-stack"><section className="wallet wallet-summary"><small>POINTS BALANCE</small><strong>{dashboard.balance.toLocaleString()}</strong><p>Available rewards balance</p></section><section className="customer-section"><div className="customer-section-head"><h2>Wallet activity</h2><span>{dashboard.transactions.length} entries</span></div><PointsActivity limit={dashboard.transactions.length}/></section></div>}
      {portalView === 'transactions' && <section className="customer-section"><div className="customer-section-head"><h2>Purchase transactions</h2><span>{dashboard.orders.length} orders</span></div><Purchases limit={dashboard.orders.length}/></section>}
      {portalView === 'notifications' && <section className="customer-section"><div className="customer-section-head"><div><h2>All notifications</h2><p>Account, points and membership updates</p></div><button className="section-link" onClick={async () => { await api.post(`/customer/${slug}/notifications/read`, {}, { showLoader: false }); loadCustomerNotifications() }}>Mark all as read</button></div><div className="customer-notifications">{customerNotifications.map(item => <button className={`notification-entry${!item.read_at ? ' unread' : ''}`} key={item.id} onClick={async () => { if (!item.read_at) await api.post(`/customer/${slug}/notifications/${item.id}/read`, {}, { showLoader: false }); if (item.data.action_url && item.data.action_url !== `/customer/${slug}`) window.location.assign(item.data.action_url); loadCustomerNotifications() }}><span><Bell size={16}/></span><div><strong>{item.data.title}</strong><p>{item.data.message}</p><small>{new Date(item.created_at).toLocaleString()}</small></div></button>)}{!customerNotifications.length && <div className="customer-empty">You have no notifications yet.</div>}</div><Pagination meta={notificationMeta} page={notificationPage} onPage={page => { setNotificationPage(page); loadCustomerNotifications(page) }}/></section>}
      {portalView === 'profile' && <div className="profile-grid">
        <form className="customer-section profile-form" onSubmit={updateProfile}>
          <div className="customer-section-head"><h2>Personal details</h2><span>Customer #{dashboard.customer.id}</span></div>
          <div className="profile-fields"><label>Full name<input name="name" required defaultValue={dashboard.customer.name || ''}/></label><label>Email address<input name="email" type="email" defaultValue={dashboard.customer.email || ''} placeholder="you@example.com"/></label><label>Registered mobile<input value={formatPhone(dashboard.customer.phone)} readOnly/></label>
            <button className="customer-primary">Save profile</button></div>
        </form>
        <section className="customer-section profile-phone"><div className="customer-section-head"><h2>Registered mobile</h2></div><div className="profile-fields"><p>Your account is secured against mobile number changes.</p><strong>{formatPhone(dashboard.customer.phone)}</strong>{profileFeedback && <div className={`${profileFeedback.type === 'error' ? 'error' : 'notice'} auth-notice`}>{profileFeedback.message}</div>}</div></section>
        <SecurityForm endpoint={`/customer/${slug}/password`} className="customer-section security-form"/>
      </div>}
      </div>
    </section>
  </main>
}

function SubscriptionGate({ user, status, onLogout }) {
  const [cycle, setCycle] = useState('monthly')
  const availablePlans = status.plans?.length ? status.plans : [status.plan].filter(Boolean)
  const [chosenPlanId, setChosenPlanId] = useState(availablePlans[0]?.id || null)
  const [checkout, setCheckout] = useState(null)
  const [authSession, setAuthSession] = useState(null)
  const [discountBody, setDiscountBody] = useState()
  const [loading, setLoading] = useState(false)
  const [cardReady, setCardReady] = useState(false)
  const [billing, setBilling] = useState({ street_1: '', city: '', postal_code: '', country: 'PK' })
  const cardRef = useRef(null)
  const authRef = useRef(null)
  const plan = availablePlans.find(item => item.id === chosenPlanId) || availablePlans[0]
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
    try {
      await api.post(`/subscription/safepay/${checkout.tracker}/processing`)
      cardRef.current.submit()
    } catch (error) {
      setLoading(false)
      toast(error.response?.data?.message || 'This secure checkout session has expired. Refresh the card fields and try again.', 'error')
      beginCheckout()
    }
  }
  return <div className="subscription-lock">
    <div className="locked-topbar"><div className="brand"><span className="brand-mark small">L</span><strong>LoyaltyOS</strong></div><button className="text-button" onClick={onLogout}>Sign out</button></div>
    <section className="subscription-dialog">
      <div className="subscription-intro"><span className="lock-icon"><CreditCard size={23}/></span><div><small>WORKSPACE ACTIVATION</small><h1>Choose your subscription</h1><p>{user.business.name} is ready. Activate the plan to unlock POS, QR codes, integrations and customer rewards.</p></div></div>
      {!plan ? <div className="no-plan"><h2>No plan is available yet</h2><p>The Super Admin must configure the platform plan before businesses can activate a subscription.</p></div> : <>
        {availablePlans.length > 1 && <div className="checkout-plan-tabs">{availablePlans.map(item => <button key={item.id} className={item.id === plan.id ? 'active' : ''} onClick={() => setChosenPlanId(item.id)}><strong>{item.name}</strong><span>{money(item.monthly_price)}/month</span></button>)}</div>}
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

function BusinessProfileGate({ user, onComplete, onLogout }) {
  const [profile, setProfile] = useState(null)
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)
  useEffect(() => { api.get('/business/profile').then(r => setProfile(r.data)).catch(err => setError(err.response?.data?.message || 'Profile could not be loaded.')) }, [])
  const submit = async e => {
    e.preventDefault(); setSaving(true); setError('')
    try { await api.put('/business/profile', Object.fromEntries(new FormData(e.currentTarget))); toast('Business profile completed.', 'success'); await onComplete() }
    catch (err) { setError(err.response?.data?.message || Object.values(err.response?.data?.errors || {})[0]?.[0] || 'Profile could not be saved.') }
    finally { setSaving(false) }
  }
  if (!profile && !error) return <div className="loading full">Loading business profile...</div>
  return <main className="profile-onboarding"><header><div className="brand"><span className="brand-mark small">L</span><strong>LoyaltyOS</strong></div><button className="text-button" onClick={onLogout}>Sign out</button></header><section className="profile-onboarding-layout"><div className="profile-onboarding-copy"><small>FINAL ONBOARDING STEP</small><h1>Complete your business profile</h1><p>Your plan is active. Add the operating details required to configure your workspace.</p><div><ShieldCheck size={19}/><span>Dashboard access unlocks after these required details are saved.</span></div></div><form className="panel profile-onboarding-form" onSubmit={submit}><div><h2>Business details</h2><p>These details can be updated later from settings.</p></div><label>Business name<input name="name" required defaultValue={profile?.name || user.business.name}/></label><label>Business category<input name="category" required defaultValue={profile?.category || ''} placeholder="Restaurant, retail, salon..."/></label><label className="wide">Street address<input name="address" required defaultValue={profile?.address || ''}/></label><label>City<input name="city" required defaultValue={profile?.city || ''}/></label><label>Country<select name="country" defaultValue={profile?.country || 'PK'}><option value="PK">Pakistan</option></select></label>{error && <div className="error wide">{error}</div>}<button className="primary wide" disabled={saving}>{saving ? 'Saving profile...' : 'Complete profile and open dashboard'}</button></form></section></main>
}

function BrandingSettings({ branding, onSaved }) {
  const [form, setForm] = useState(branding)
  const [logo, setLogo] = useState(null)
  const [preview, setPreview] = useState(branding.logo_url)
  const [error, setError] = useState('')
  const [saving, setSaving] = useState(false)
  useEffect(() => { setForm(branding); setPreview(branding.logo_url) }, [branding])
  useEffect(() => {
    if (!logo) return undefined
    const url = URL.createObjectURL(logo)
    setPreview(url)
    return () => URL.revokeObjectURL(url)
  }, [logo])
  const change = e => setForm(value => ({ ...value, [e.target.name]: e.target.value }))
  const submit = async e => {
    e.preventDefault(); setSaving(true); setError('')
    const payload = new FormData()
    payload.append('brand_name', form.brand_name || '')
    payload.append('brand_primary_color', form.brand_primary_color)
    payload.append('brand_accent_color', form.brand_accent_color)
    payload.append('brand_text_color', form.brand_text_color)
    if (logo) payload.append('logo', logo)
    try { const { data } = await api.post('/business/branding', payload); setLogo(null); onSaved(data); toast('Branding updated.', 'success') }
    catch (err) { setError(err.response?.data?.message || Object.values(err.response?.data?.errors || {})[0]?.[0] || 'Branding could not be saved.') }
    finally { setSaving(false) }
  }
  const reset = async () => {
    setSaving(true); setError('')
    try { const { data } = await api.delete('/business/branding'); setLogo(null); onSaved(data); toast('Default branding restored.', 'success') }
    catch (err) { setError(err.response?.data?.message || 'Branding could not be reset.') }
    finally { setSaving(false) }
  }
  const previewBrand = { ...form, logo_url: preview }
  return <div className="branding-layout">
    <form className="panel branding-form" onSubmit={submit}>
      <div className="panel-head"><div><h2>Business branding</h2><p>Customize the workspace and customer rewards portal</p></div></div>
      <div className="branding-fields">
        <label className="wide">Brand name<input name="brand_name" value={form.brand_name || ''} onChange={change} maxLength="80" placeholder="Business name"/></label>
        <label>Primary color<div className="color-control"><input type="color" name="brand_primary_color" value={form.brand_primary_color} onChange={change}/><input name="brand_primary_color" value={form.brand_primary_color} onChange={change} pattern="#[0-9a-fA-F]{6}"/></div></label>
        <label>Accent color<div className="color-control"><input type="color" name="brand_accent_color" value={form.brand_accent_color} onChange={change}/><input name="brand_accent_color" value={form.brand_accent_color} onChange={change} pattern="#[0-9a-fA-F]{6}"/></div></label>
        <label>Text color<div className="color-control"><input type="color" name="brand_text_color" value={form.brand_text_color} onChange={change}/><input name="brand_text_color" value={form.brand_text_color} onChange={change} pattern="#[0-9a-fA-F]{6}"/></div></label>
        <label className="wide">Logo<input type="file" accept="image/png,image/jpeg,image/webp" onChange={e => setLogo(e.target.files?.[0] || null)}/><small>PNG, JPG or WebP, up to 2 MB.</small></label>
        {error && <div className="error wide">{error}</div>}
        <div className="branding-actions wide"><button className="primary" disabled={saving}>{saving ? 'Saving...' : 'Save branding'}</button><button type="button" className="secondary" onClick={reset} disabled={saving}>Restore defaults</button></div>
      </div>
    </form>
    <section className="panel branding-preview" style={brandingStyle(previewBrand)}>
      <div className="panel-head"><div><h2>Preview</h2><p>Workspace identity</p></div></div>
      <div className="branding-preview-sidebar"><div className="brand"><BrandLogo branding={previewBrand} small/><strong>{form.brand_name || 'Business name'}</strong></div><div className="branding-preview-link active"><LayoutDashboard size={17}/>Overview</div><div className="branding-preview-link"><Users size={17}/>Customers</div><button type="button">Primary action</button></div>
    </section>
  </div>
}

function LoyaltyManagement() {
  const [data, setData] = useState(null)
  const [tab, setTab] = useState('setup')
  const [tour, setTour] = useState(null)
  const [editingRule, setEditingRule] = useState(null)
  const [editingLevel, setEditingLevel] = useState(null)
  const [levelName, setLevelName] = useState('Silver')
  const [benefits, setBenefits] = useState(tierPresets.Silver)
  const load = () => api.get('/business/loyalty').then(response => setData(response.data))
  useEffect(() => { load() }, [])
  useEffect(() => {
    if (!data) return
    const key = tab === 'levels' ? 'membership' : tab === 'rewards' ? 'rewards' : 'loyalty'
    if (!data.settings.completed_tours?.includes(key)) setTour(key)
  }, [data, tab])
  useEffect(() => {
    if (!editingLevel) return
    setLevelName(editingLevel.name)
    setBenefits(editingLevel.benefits?.length ? editingLevel.benefits : tierPresets[editingLevel.name])
  }, [editingLevel])
  useEffect(() => {
    const nameInput = document.querySelector('.loyalty-form input[name="name"]')
    const orderInput = document.querySelector('.loyalty-form input[name="display_order"]')
    if (nameInput) nameInput.value = levelName
    if (orderInput) orderInput.value = Object.keys(tierPresets).indexOf(levelName) + 1
  }, [levelName, editingLevel, tab])
  if (!data) return <div className="loading">Loading loyalty program...</div>
  const saveSettings = async next => { await api.put('/business/loyalty/settings', next); await load() }
  const createRule = async event => { event.preventDefault(); const form = event.currentTarget; const values = Object.fromEntries(new FormData(form)); values.active = values.active === '1'; if (editingRule) await api.put(`/business/loyalty/rules/${editingRule.id}`, values); else await api.post('/business/loyalty/rules', values); setEditingRule(null); form.reset(); await load() }
  const createLevel = async event => {
    event.preventDefault(); const form = event.currentTarget; const values = Object.fromEntries(new FormData(form)); values.name = levelName; values.benefits = benefits.map(value => value.trim()).filter(Boolean); values.active = values.active === '1'
    if (editingLevel) await api.put(`/business/loyalty/levels/${editingLevel.id}`, values); else await api.post('/business/loyalty/levels', values); setEditingLevel(null); setLevelName('Silver'); setBenefits(tierPresets.Silver); form.reset(); await load()
  }
  const steps = [['profile', 'Complete business profile'], ['loyalty', 'Enable loyalty program'], ['points_rule', 'Configure points rules'], ['membership_level', 'Create membership levels'], ['qr', 'Configure QR'], ['integration', 'Connect a website']]
  const selectLevel = name => { setLevelName(name); setBenefits(tierPresets[name]) }
  const locked = tab === 'rules' ? !data.settings.points_enabled : ['levels', 'rewards'].includes(tab) ? !data.settings.memberships_enabled : false
  const examples = Object.values(tierPresets).flat()
  const tourCopy = {
    loyalty: ['Configure your loyalty program', 'Enable the program first, then choose whether customers earn points and use membership levels. Points rules only affect future paid orders.'],
    membership: ['Build membership levels', 'Add levels in ascending display order with unique point thresholds, badge styling and customer-facing benefits.'],
    rewards: ['Review customer benefits', 'This view shows the rewards customers receive at each active membership level.'],
  }
  const closeTour = async () => { const active = tour; setTour(null); if (active && !data.settings.completed_tours?.includes(active)) { await api.post('/business/loyalty/tours', { tour: active }, { showLoader: false }); await load() } }
  return <div className={`loyalty-workspace${locked ? ' is-locked' : ''}${tab === 'levels' ? ' levels-layout' : ''}`}>
    <div className="loyalty-navigation"><section className="loyalty-tabs">{[['setup', 'Overview'], ['rules', 'Points Rules'], ['levels', 'Membership Levels'], ['rewards', 'Rewards & Benefits']].map(([key, label]) => <button className={tab === key ? 'active' : ''} key={key} onClick={() => setTab(key)}>{label}</button>)}</section><button className="secondary" onClick={() => setTour(tab === 'levels' ? 'membership' : tab === 'rewards' ? 'rewards' : 'loyalty')}>Help</button></div>
    {locked && <section className="feature-locked"><ShieldCheck size={30}/><h2>{tab === 'rules' ? 'Customer Points is disabled' : 'Membership Levels is disabled'}</h2><p>Enable this feature from Overview before configuring it.</p><button className="primary" onClick={() => setTab('setup')}>Open general settings</button></section>}
    {tab === 'levels' && !locked && <section className="panel tier-preset-editor"><div className="panel-head"><div><h2>Membership setup</h2><p>Choose a fixed membership type and customize its benefits</p></div></div><div className="tier-preset-body"><div className="tier-type-field"><strong>Membership type</strong><div className="tier-type-options">{Object.keys(tierPresets).map(name => { const Icon = tierIcons[name]; return <button type="button" key={name} className={levelName === name ? 'active' : ''} disabled={Boolean(editingLevel)} onClick={() => selectLevel(name)}><Icon size={19}/><span>{name}</span></button> })}</div><small>The icon and display order are assigned automatically.</small></div><div className="benefit-editor"><strong>Included benefits</strong>{benefits.map((benefit, index) => <div key={index}><input value={benefit} onChange={event => setBenefits(values => values.map((value, itemIndex) => itemIndex === index ? event.target.value : value))}/><button type="button" title="Remove benefit" onClick={() => setBenefits(values => values.filter((_, itemIndex) => itemIndex !== index))}><X size={15}/></button></div>)}<button type="button" className="secondary" onClick={() => setBenefits(values => [...values, ''])}><Plus size={15}/>Add benefit</button></div></div></section>}
    {tab === 'setup' && <div className="loyalty-columns"><section className="panel"><div className="panel-head"><div><h2>General settings</h2><p>Control which loyalty features customers can use</p></div></div><div className="toggle-list">
      {[['loyalty_enabled', 'Enable loyalty program'], ['points_enabled', 'Enable customer points'], ['memberships_enabled', 'Enable membership levels']].map(([key, label]) => <label key={key}><span><strong>{label}</strong><small>{key === 'loyalty_enabled' ? 'Master control for all rewards features' : `Show and calculate ${label.toLowerCase()}`}</small></span><input type="checkbox" checked={data.settings[key]} disabled={key !== 'loyalty_enabled' && !data.settings.loyalty_enabled} onChange={event => saveSettings({ ...data.settings, [key]: event.target.checked })}/></label>)}
      <label className="downgrade-policy"><span><strong>Tier downgrade policy</strong><small>Time allowed to recover required points after redemption</small></span><select value={data.settings.membership_downgrade_grace_days ?? ''} disabled={!data.settings.loyalty_enabled || !data.settings.memberships_enabled} onChange={event => saveSettings({ ...data.settings, membership_downgrade_grace_days: event.target.value ? Number(event.target.value) : null })}><option value="">Never downgrade</option><option value="15">15 days</option><option value="30">30 days</option><option value="60">60 days</option></select></label>
    </div></section><section className="panel"><div className="panel-head"><div><h2>Setup progress</h2><p>Initial workspace checklist</p></div></div><div className="onboarding-list">{steps.map(([key, label]) => <div key={key} className={data.onboarding[key] ? 'complete' : ''}><BadgeCheck size={18}/><span>{label}</span><strong>{data.onboarding[key] ? 'Complete' : 'Pending'}</strong></div>)}</div></section></div>}
    {tab === 'rules' && <div className="loyalty-columns"><section className="panel"><div className="panel-head"><div><h2>Points rules</h2><p>Highest eligible purchase threshold applies to each new order</p></div></div><div className="loyalty-list">{data.rules.map(rule => <div key={rule.id}><div><strong>{money(rule.purchase_amount)} = {rule.earned_points} points</strong><small>{rule.active ? 'Active' : 'Inactive'} · Existing ledger entries never change</small></div><button title="Edit rule" onClick={() => setEditingRule(rule)}><Settings2 size={16}/></button><button title="Delete rule" onClick={async () => { await api.delete(`/business/loyalty/rules/${rule.id}`); load() }}><Trash2 size={16}/></button></div>)}{!data.rules.length && <div className="customer-empty">No points rule configured.</div>}</div></section><form key={editingRule?.id || 'new-rule'} className="panel loyalty-form" onSubmit={createRule}><div className="panel-head"><div><h2>{editingRule ? 'Edit points rule' : 'Add points rule'}</h2><p>Duplicate purchase amounts are not allowed</p></div>{editingRule && <button type="button" className="text-button" onClick={() => setEditingRule(null)}>Cancel</button>}</div><div><label>Purchase amount (PKR)<input name="purchase_amount" type="number" min="1" step="0.01" required defaultValue={editingRule?.purchase_amount}/></label><label>Earned points<input name="earned_points" type="number" min="1" required defaultValue={editingRule?.earned_points}/></label><label className="check-label"><input name="active" type="checkbox" value="1" defaultChecked={editingRule?.active ?? true}/>Active</label><button className="primary">{editingRule ? 'Save rule' : 'Add rule'}</button></div></form></div>}
    {tab === 'levels' && <div className="loyalty-columns"><section className="panel"><div className="panel-head"><div><h2>Membership levels</h2><p>Thresholds must increase with display order</p></div></div><div className="loyalty-list">{data.levels.map(level => <div key={level.id}><span className="level-icon" style={{ color: level.badge_color }}><TierIcon name={level.name}/></span><div><strong>{level.name} · {level.required_points.toLocaleString()} points</strong><small>Order {level.display_order} · {level.active ? 'Active' : 'Inactive'} · {level.benefits?.length || 0} benefits</small></div><button title="Edit level" onClick={() => setEditingLevel(level)}><Settings2 size={16}/></button><button title="Delete level" onClick={async () => { await api.delete(`/business/loyalty/levels/${level.id}`); load() }}><Trash2 size={16}/></button></div>)}{!data.levels.length && <div className="customer-empty">No membership levels configured.</div>}</div></section><form key={editingLevel?.id || 'new-level'} className="panel loyalty-form" onSubmit={createLevel}><div className="panel-head"><div><h2>{editingLevel ? 'Edit membership level' : 'Create membership level'}</h2><p>Customers resolve to the highest active eligible level</p></div>{editingLevel && <button type="button" className="text-button" onClick={() => setEditingLevel(null)}>Cancel</button>}</div><div><label>Level name<input name="name" required placeholder="Gold" defaultValue={editingLevel?.name}/></label><label>Required points<input name="required_points" type="number" min="0" required defaultValue={editingLevel?.required_points}/></label><label>Display order<input name="display_order" type="number" min="1" required defaultValue={editingLevel?.display_order}/></label><label>Badge color<input name="badge_color" type="color" defaultValue={editingLevel?.badge_color || '#e4b94e'}/></label><label>Badge icon<select name="icon" defaultValue={editingLevel?.icon || 'badge'}><option value="badge">Badge</option><option value="star">Star</option><option value="crown">Crown</option><option value="diamond">Diamond</option></select></label><label>Benefits, one per line<textarea name="benefits" rows="5" defaultValue={editingLevel?.benefits?.join('\n')} placeholder={examples.join('\n')}/></label><label className="check-label"><input name="active" type="checkbox" value="1" defaultChecked={editingLevel?.active ?? true}/>Active</label><button className="primary">{editingLevel ? 'Save level' : 'Create level'}</button></div></form></div>}
    {tab === 'rewards' && <section className="panel"><div className="panel-head"><div><h2>Rewards & benefits</h2><p>Benefits configured on active membership levels</p></div></div><div className="benefit-levels">{data.levels.filter(level => level.active).map(level => <article key={level.id}><div className="benefit-level-title" style={{ color: level.badge_color }}><TierIcon name={level.name} size={22}/><h3>{level.name}</h3></div>{level.benefits?.map(benefit => <p key={benefit}><BadgeCheck size={14}/>{benefit}</p>)}{!level.benefits?.length && <small>No benefits configured.</small>}</article>)}{!data.levels.some(level => level.active) && <div className="customer-empty">Create an active membership level to configure benefits.</div>}</div></section>}
    {tour && <div className="tour-overlay" role="dialog" aria-modal="true"><section><button className="tour-close" title="Close tour" onClick={closeTour}><X size={18}/></button><span><BadgeCheck size={23}/></span><small>QUICK TOUR</small><h2>{tourCopy[tour][0]}</h2><p>{tourCopy[tour][1]}</p><button className="primary" onClick={closeTour}>Got it</button></section></div>}
  </div>
}

function Shell({ user, onLogout }) {
  const [view, setView] = useState(() => hashView('Overview'))
  const [revision, setRevision] = useState(0)
  const [subscription, setSubscription] = useState(null)
  const [notifications, setNotifications] = useState([])
  const [notificationMeta, setNotificationMeta] = useState(null)
  const [notificationPage, setNotificationPage] = useState(1)
  const [unreadCount, setUnreadCount] = useState(0)
  const [branding, setBranding] = useState({ ...defaultBranding, brand_name: user.business.name })
  const [showNotifications, setShowNotifications] = useState(false)
  const loadSubscription = () => api.get('/subscription').then(r => setSubscription(r.data))
  const loadNotifications = (page = notificationPage) => api.get('/notifications', { params: { page }, showLoader: false }).then(r => { setNotifications(r.data.data || r.data); setNotificationMeta(r.data.meta || r.data); setUnreadCount(r.data.unread_count || 0) }).catch(() => {})
  useDismissable(showNotifications, () => setShowNotifications(false))
  useEffect(() => { loadSubscription(); loadNotifications() }, [])
  useEffect(() => {
    if (subscription?.subscription && !subscription.profile_required && subscription.business?.profile_completed) {
      api.get('/business/branding', { showLoader: false }).then(r => setBranding({ ...r.data, loaded: true })).catch(() => setBranding(value => ({ ...value, loaded: true })))
    }
  }, [subscription?.subscription, subscription?.profile_required, subscription?.business?.profile_completed])
  useEffect(() => connectRealtime(user.business.id, () => setRevision(value => value + 1)), [user.business.id])
  useEffect(() => connectNotifications(user.id, loadNotifications), [user.id])
  if (!subscription) return <div className="loading full">Loading subscription...</div>
  if (!subscription.subscription) return <SubscriptionGate user={user} status={subscription} onLogout={onLogout}/>
  if (subscription.profile_required || !subscription.business?.profile_completed) return <BusinessProfileGate user={user} onComplete={loadSubscription} onLogout={onLogout}/>
  const items = [
    ['Overview', LayoutDashboard], ['POS', ReceiptText], ['QR codes', QrCode],
    ['Integrations', Globe2], ['Customer Loyalty', BadgeCheck], ['Customers', Users], ['Notifications', Bell], ['Settings', Settings2],
  ]
  return <div className="app-shell" style={brandingStyle(branding)}>
    <aside>
      <div className="brand"><BrandLogo branding={branding} small/><strong>{branding.brand_name}</strong></div>
      <nav>{items.map(([name, Icon]) => <button key={name} className={view === name ? 'active' : ''} onClick={() => setView(name)}><Icon size={18} />{name}</button>)}</nav>
      <div className="aside-bottom">
        <div className="account"><span>{user.name[0]}</span><div><strong>{user.name}</strong><small>{user.business.name}</small></div><button title="Log out" onClick={onLogout}><LogOut size={17}/></button></div>
      </div>
    </aside>
    <main className="workspace">
      <header><div><h1>{view}</h1><p>{user.business.name} · {subscription.subscription.plan.name} plan</p></div><div className="workspace-actions"><button className="icon-button notification-button" title="Notifications" aria-label={`Notifications, ${unreadCount} unread`} aria-expanded={showNotifications} onClick={() => setShowNotifications(value => !value)}><Bell size={19}/>{unreadCount > 0 && <span className="notification-count">{unreadCount > 99 ? '99+' : unreadCount}</span>}</button>{showNotifications && <div className="notification-menu"><div><strong>Notifications</strong><button onClick={async () => { await api.post('/notifications/read', {}, { showLoader: false }); await loadNotifications() }}>Mark all read</button></div>{notifications.slice(0, 6).map(item => <button className={`notification-entry${!item.read_at ? ' unread' : ''}`} key={item.id} onClick={async () => { if (!item.read_at) await api.post(`/notifications/${item.id}/read`, {}, { showLoader: false }); setShowNotifications(false); if (item.data.action_url) window.location.assign(item.data.action_url); else setView('Notifications'); loadNotifications() }}><strong>{item.data.title}</strong><p>{item.data.message}</p><small>{new Date(item.created_at).toLocaleString()}</small></button>)}{!notifications.length && <p className="customer-empty">No notifications yet.</p>}<button className="notification-view-all" onClick={() => { setView('Notifications'); setShowNotifications(false) }}>View all notifications</button></div>}</div></header>
      {view === 'Overview' && <Overview revision={revision} customerUrl={subscription.customer_portal_url} />}
      {view === 'POS' && <Pos />}
      {view === 'QR codes' && <QRCodes />}
      {view === 'Integrations' && <Integrations plan={subscription.subscription.plan} />}
      {view === 'Customer Loyalty' && <LoyaltyManagement />}
      {view === 'Customers' && <Customers />}
      {view === 'Notifications' && <section className="panel notification-page"><div className="panel-head"><div><h2>All notifications</h2><p>Updates for your workspace, newest first</p></div><button className="secondary" onClick={async () => { await api.post('/notifications/read', {}, { showLoader: false }); loadNotifications() }}>Mark all as read</button></div><div>{notifications.map(item => <button key={item.id} className={`notification-entry${!item.read_at ? ' unread' : ''}`} onClick={async () => { if (!item.read_at) await api.post(`/notifications/${item.id}/read`, {}, { showLoader: false }); if (item.data.action_url) window.location.assign(item.data.action_url); loadNotifications() }}><span><Bell size={18}/></span><div><strong>{item.data.title}</strong><p>{item.data.message}</p><small>{new Date(item.created_at).toLocaleString()}</small></div></button>)}{!notifications.length && <div className="customer-empty">No notifications yet.</div>}</div><Pagination meta={notificationMeta} page={notificationPage} onPage={page => { setNotificationPage(page); loadNotifications(page) }}/></section>}
      {view === 'Settings' && <div className="settings-stack"><BrandingSettings branding={branding} onSaved={data => setBranding({ ...data, loaded: true })}/><SecurityForm /></div>}
    </main>
  </div>
}

function BusinessOverviewCharts({ charts }) {
  const monthly = charts?.monthly || []
  const maxRevenue = Math.max(1, ...monthly.map(item => item.revenue))
  const maxOrders = Math.max(1, ...monthly.map(item => item.orders))
  const maxCustomers = Math.max(1, ...monthly.map(item => item.customers))
  return <section className="business-overview-charts"><article className="panel"><div className="panel-head"><div><h2>Revenue trend</h2><p>Paid customer-order revenue for the last six months</p></div></div><div className="bar-chart revenue-chart">{monthly.map(item => <div key={item.label}><span style={{ height: `${Math.max(5, item.revenue / maxRevenue * 100)}%` }}/><strong>{money(item.revenue)}</strong><small>{item.label}</small></div>)}</div></article><article className="panel"><div className="panel-head"><div><h2>Orders and customer growth</h2><p>Paid orders and newly registered customers</p></div></div><div className="dual-trend-chart"><div className="chart-legend"><span><i className="orders"/>Paid orders</span><span><i className="customers"/>New customers</span></div><div>{monthly.map(item => <div key={item.label}><section><i className="orders" style={{ height: `${Math.max(5, item.orders / maxOrders * 100)}%` }}/><i className="customers" style={{ height: `${Math.max(5, item.customers / maxCustomers * 100)}%` }}/></section><strong>{item.orders} / {item.customers}</strong><small>{item.label}</small></div>)}</div></div></article></section>
}

function Overview({ revision, customerUrl }) {
  const [data, setData] = useState(null)
  const [orderSearch, setOrderSearch] = useState('')
  const [showPortalQr, setShowPortalQr] = useState(false)
  const portalQrRef = useRef(null)
  useEffect(() => { api.get('/dashboard').then(r => setData(r.data)) }, [revision])
  if (!data) return <div className="loading">Loading workspace...</div>
  const cards = [
    ['Revenue', money(data.metrics.revenue)], ['Paid orders', data.metrics.orders],
    ['Customers', data.metrics.customers], ['Memberships', data.metrics.memberships],
  ]
  const shareText = `Open our customer rewards portal: ${customerUrl}`
  const downloadQr = () => {
    const svg = portalQrRef.current?.querySelector('svg')
    if (!svg) return
    const blob = new Blob([new XMLSerializer().serializeToString(svg)], { type: 'image/svg+xml' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a'); link.href = url; link.download = 'customer-rewards-portal-qr.svg'; link.click()
    URL.revokeObjectURL(url)
  }
  const nativeShare = async () => {
    if (navigator.share) await navigator.share({ title: 'Customer rewards portal', text: shareText, url: customerUrl })
    else { await navigator.clipboard.writeText(customerUrl); toast('Portal link copied.', 'success') }
  }
  const recentOrders = data.recent_orders.filter(order => [order.external_id, order.customer?.name, order.customer?.phone, order.source, order.status].filter(Boolean).join(' ').toLowerCase().includes(orderSearch.toLowerCase()))
  return <><section className="metrics">{cards.map(([k, v]) => <article key={k}><small>{k}</small><strong>{v}</strong></article>)}</section>
    <BusinessOverviewCharts charts={data.charts}/>
    <section className="portal-share"><div><Globe2 size={20}/><div><strong>Customer rewards portal</strong><small>Generate a QR code or share this public link with customers.</small></div></div><code>{customerUrl}</code><div className="portal-share-actions"><button className="secondary" onClick={async () => { await navigator.clipboard.writeText(customerUrl); toast('Portal link copied.', 'success') }}>Copy link</button><button className="primary" onClick={() => setShowPortalQr(value => !value)}><QrCode size={16}/>{showPortalQr ? 'Hide QR' : 'Generate QR'}</button></div></section>
    {showPortalQr && <section className="panel portal-qr-panel"><div ref={portalQrRef} className="portal-qr-code"><QRCodeSVG value={customerUrl} size={190} level="H" includeMargin/><small>Scanning opens</small><strong>{customerUrl}</strong></div><div className="portal-qr-copy"><h2>Share customer rewards portal</h2><p>Customers who scan this QR code will open the rewards portal directly.</p><div><button className="secondary" onClick={downloadQr}>Download QR</button><button className="secondary" onClick={() => window.open(`https://wa.me/?text=${encodeURIComponent(shareText)}`, '_blank', 'noopener,noreferrer')}>WhatsApp</button><button className="secondary" onClick={() => window.open(`https://mail.google.com/mail/?view=cm&fs=1&su=${encodeURIComponent('Customer rewards portal')}&body=${encodeURIComponent(shareText)}`, '_blank', 'noopener,noreferrer')}>Gmail</button><button className="primary" onClick={nativeShare}>Share</button></div></div></section>}
    <section className="panel"><div className="panel-head"><div><h2>Recent orders</h2><p>Latest activity across connected sales channels</p></div></div><div className="table-toolbar"><label className="search-field"><Search size={16}/><input value={orderSearch} onChange={event => setOrderSearch(event.target.value)} placeholder="Search recent orders" aria-label="Search recent orders"/></label></div>
      <table><thead><tr><th>Order</th><th>Customer</th><th>Source</th><th>Status</th><th className="right">Total</th></tr></thead>
        <tbody>{recentOrders.map(o => <tr key={o.id}><td>{o.external_id}</td><td>{o.customer?.name || o.customer?.phone || 'Walk-in'}</td><td>{o.source}</td><td><span className="status">{o.status}</span></td><td className="right">{money(o.total)}</td></tr>)}
        {!recentOrders.length && <tr><td colSpan="5" className="empty-row">No recent orders match your search.</td></tr>}</tbody></table>
    </section></>
}

function Customers() {
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [result, setResult] = useState(null)
  useEffect(() => {
    const timer = window.setTimeout(() => api.get('/business/customers', { params: { search: search || undefined, page }, showLoader: false }).then(r => setResult(r.data)), 250)
    return () => window.clearTimeout(timer)
  }, [search, page])
  return <section className="panel customer-directory"><div className="panel-head"><div><h2>Customers</h2><p>Search customer profiles, balances and memberships</p></div><span className="status">{result?.total || 0} customers</span></div><div className="table-toolbar"><label className="search-field"><Search size={16}/><input value={search} onChange={event => { setSearch(event.target.value); setPage(1) }} placeholder="Search name, email or mobile" aria-label="Search customers"/></label></div><table><thead><tr><th>Customer</th><th>Mobile</th><th>Membership</th><th>Orders</th><th className="right">Points</th></tr></thead><tbody>{(result?.data || []).map(customer => <tr key={customer.id}><td><strong>{customer.name || 'Unnamed customer'}</strong><small className="table-sub">{customer.email || 'No email'}</small></td><td>{customer.phone}</td><td><span className="status">{customer.current_membership?.level?.name || 'Member'}</span></td><td>{customer.orders_count}</td><td className="right">{Number(customer.points_balance || 0).toLocaleString()}</td></tr>)}{result && !result.data.length && <tr><td colSpan="5" className="empty-row">No customers match your search.</td></tr>}</tbody></table><Pagination meta={result} page={page} onPage={setPage}/></section>
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
      <label>Customer phone<PhoneInput required /></label>
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

export default function App() {
  const claimToken = window.location.pathname.match(/^\/claim\/([^/]+)$/)?.[1]
  const customerSlug = window.location.pathname.match(/^\/customer\/([^/]+)\/?$/)?.[1]
  const isAdmin = /^\/admin\/?$/.test(window.location.pathname)
  const [user, setUser] = useState(undefined)
  useEffect(() => {
    if (claimToken || customerSlug) return
    api.get('/me', { showLoader: false }).then(r => setUser(r.data)).catch(() => setUser(null))
  }, [claimToken, customerSlug])
  if (claimToken) return <ClaimPage token={claimToken}/>
  if (customerSlug) return <CustomerPortal slug={customerSlug}/>
  if (user === undefined) return <AuthLoadingScreen label={isAdmin ? 'Loading control center...' : 'Loading your workspace...'}/>
  if (isAdmin) return <AdminPortal user={user} setUser={setUser}/>
  if (!user) return <Login onLogin={setUser} />
  if (user.role === 'super_admin') {
    window.location.replace('/admin')
    return null
  }
  const logout = async () => { await api.post('/logout'); setUser(null) }
  return <Shell user={user} onLogout={logout}/>
}
