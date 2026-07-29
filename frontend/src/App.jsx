import { useEffect, useState } from 'react'
import { QRCodeSVG } from 'qrcode.react'
import {
  BarChart3, Globe2, LayoutDashboard, LogOut, MonitorSmartphone,
  Plus, QrCode, ReceiptText, Search, Settings2, Users,
} from 'lucide-react'
import { api, login, prepareCsrf } from './api'
import { connectRealtime } from './realtime'
import './styles.css'

const money = (value) => new Intl.NumberFormat('en-PK', { style: 'currency', currency: 'PKR', maximumFractionDigits: 0 }).format(value || 0)

function Login({ onLogin }) {
  const [email, setEmail] = useState('owner@example.com')
  const [password, setPassword] = useState('password')
  const [error, setError] = useState('')
  const submit = async (event) => {
    event.preventDefault()
    try { onLogin(await login(email, password)) } catch (e) { setError(e.response?.data?.message || 'Login failed') }
  }
  return <main className="login-shell">
    <form className="login-panel" onSubmit={submit}>
      <div className="brand-mark">L</div>
      <h1>LoyaltyOS</h1>
      <p>Sign in to your business workspace</p>
      <label>Email<input value={email} onChange={e => setEmail(e.target.value)} type="email" /></label>
      <label>Password<input value={password} onChange={e => setPassword(e.target.value)} type="password" /></label>
      {error && <div className="error">{error}</div>}
      <button className="primary">Sign in</button>
    </form>
  </main>
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
  const [step, setStep] = useState('phone')
  const [phone, setPhone] = useState('')
  const [code, setCode] = useState('')
  const [demoCode, setDemoCode] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    api.get(`/customer/${slug}/business`).then(r => setBusiness(r.data)).catch(() => setError('Business portal not found.'))
    api.get(`/customer/${slug}/dashboard`).then(r => setDashboard(r.data)).catch(() => {})
  }, [slug])

  const requestCode = async e => {
    e.preventDefault(); setError('')
    try {
      await prepareCsrf()
      const { data } = await api.post(`/customer/${slug}/otp`, { phone })
      setDemoCode(data.demo_code || ''); setStep('code')
    } catch (err) { setError(err.response?.data?.message || 'Could not send code.') }
  }
  const verify = async e => {
    e.preventDefault(); setError('')
    try {
      const { data } = await api.post(`/customer/${slug}/verify`, { phone, code })
      setDashboard(data)
    } catch (err) { setError(err.response?.data?.message || 'Invalid code.') }
  }
  const logout = async () => { await api.post(`/customer/${slug}/logout`); setDashboard(null); setStep('phone'); setCode('') }

  if (!business && !error) return <div className="loading full">Loading rewards...</div>
  if (!dashboard) return <main className="customer-login">
    <section className="customer-login-panel">
      <div className="customer-brand"><span className="brand-mark">L</span><div><small>REWARDS BY LOYALTYOS</small><h1>{business?.name || 'Rewards'}</h1></div></div>
      <div className="login-copy"><h2>Your rewards, in one place</h2><p>Check your points and recent purchases using your phone number.</p></div>
      {step === 'phone' ? <form onSubmit={requestCode} className="claim-form">
        <label>Mobile number<input value={phone} onChange={e => setPhone(e.target.value)} required placeholder="+92 300 1234567"/></label>
        {error && <div className="error">{error}</div>}
        <button className="customer-primary">Send secure code</button>
      </form> : <form onSubmit={verify} className="claim-form">
        <label>6-digit code<input value={code} onChange={e => setCode(e.target.value)} required inputMode="numeric" maxLength="6" placeholder="000000"/></label>
        {demoCode && <div className="demo-code">Development code: <strong>{demoCode}</strong></div>}
        {error && <div className="error">{error}</div>}
        <button className="customer-primary">Open my rewards</button>
        <button type="button" className="text-button" onClick={() => setStep('phone')}>Use another number</button>
      </form>}
      <p className="privacy-note">Your number is only used to identify your rewards account.</p>
    </section>
  </main>

  const progress = dashboard.next_tier_at ? Math.min(100, (dashboard.balance / dashboard.next_tier_at) * 100) : 100
  return <main className="customer-portal">
    <header className="customer-header"><div className="customer-brand"><span className="brand-mark small">L</span><strong>{dashboard.business.name}</strong></div><button className="text-button" onClick={logout}>Sign out</button></header>
    <section className="customer-content">
      <div className="customer-welcome"><div><p>Welcome back</p><h1>{dashboard.customer.name || dashboard.customer.phone}</h1></div><span className="tier-badge">{dashboard.tier}</span></div>
      <section className="wallet">
        <small>AVAILABLE POINTS</small><strong>{dashboard.balance.toLocaleString()}</strong><p>Use your points on your next eligible purchase.</p>
        <div className="tier-progress"><div><span>{dashboard.tier}</span><span>{dashboard.next_tier || 'Top tier'}</span></div><progress value={progress} max="100"/>
          <small>{dashboard.next_tier ? `${Math.max(0, dashboard.next_tier_at - dashboard.balance)} points to ${dashboard.next_tier}` : 'You reached the highest tier'}</small></div>
      </section>
      <div className="customer-grid">
        <section className="customer-section"><div className="customer-section-head"><h2>Points activity</h2><span>{dashboard.transactions.length} entries</span></div>
          <div className="activity-list">{dashboard.transactions.map(tx => <div className="activity-row" key={tx.id}><div className={tx.points >= 0 ? 'activity-icon earn' : 'activity-icon spend'}>{tx.points >= 0 ? '+' : '−'}</div><div><strong>{tx.description || tx.type}</strong><small>{new Date(tx.created_at).toLocaleDateString()}</small></div><b className={tx.points >= 0 ? 'positive' : 'negative'}>{tx.points >= 0 ? '+' : ''}{tx.points}</b></div>)}
            {!dashboard.transactions.length && <div className="customer-empty">Your points activity will appear here.</div>}</div>
        </section>
        <section className="customer-section"><div className="customer-section-head"><h2>Recent purchases</h2><span>{dashboard.orders.length} orders</span></div>
          <div className="activity-list">{dashboard.orders.map(order => <div className="activity-row" key={order.id}><div className="activity-icon order"><ReceiptText size={17}/></div><div><strong>{order.external_id}</strong><small>{new Date(order.created_at).toLocaleDateString()} · {order.payment_method || order.source}</small></div><b>{money(order.total)}</b></div>)}
            {!dashboard.orders.length && <div className="customer-empty">No linked purchases yet.</div>}</div>
        </section>
      </div>
    </section>
  </main>
}

function Shell({ user, onLogout }) {
  const [view, setView] = useState('Overview')
  const [revision, setRevision] = useState(0)
  useEffect(() => connectRealtime(user.business.id, () => setRevision(value => value + 1)), [user.business.id])
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
      <header><div><h1>{view}</h1><p>{user.business.name} · {user.business.plan.name} plan</p></div><button className="icon-button" title="Search"><Search size={19}/></button></header>
      {view === 'Overview' && <Overview revision={revision} />}
      {view === 'POS' && <Pos />}
      {view === 'QR codes' && <QRCodes />}
      {view === 'Integrations' && <Integrations user={user} />}
      {['Customers', 'Analytics'].includes(view) && <Empty title={view} />}
    </main>
  </div>
}

function Overview({ revision }) {
  const [data, setData] = useState(null)
  useEffect(() => { api.get('/dashboard').then(r => setData(r.data)) }, [revision])
  if (!data) return <div className="loading">Loading workspace...</div>
  const cards = [
    ['Revenue', money(data.metrics.revenue)], ['Paid orders', data.metrics.orders],
    ['Customers', data.metrics.customers], ['Points issued', data.metrics.points_issued],
  ]
  return <><section className="metrics">{cards.map(([k, v]) => <article key={k}><small>{k}</small><strong>{v}</strong></article>)}</section>
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

function Integrations({ user }) {
  const [data, setData] = useState({ domains: [], integrations: [] })
  const [url, setUrl] = useState('')
  const [secret, setSecret] = useState('')
  const load = () => api.get('/integrations').then(r => setData(r.data))
  useEffect(() => { load() }, [])
  const addDomain = async e => { e.preventDefault(); await api.post('/domains', { url }); setUrl(''); load() }
  const addKey = async () => { const r = await api.post('/integrations', { name: 'Website checkout' }); setSecret(r.data.secret); load() }
  return <div className="stack">
    <section className="panel"><div className="panel-head"><div><h2>Verified domains</h2><p>{data.domains.length} of {user.business.plan.domain_limit} used</p></div></div>
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
  const [user, setUser] = useState(undefined)
  useEffect(() => { api.get('/me').then(r => setUser(r.data)).catch(() => setUser(null)) }, [])
  if (claimToken) return <ClaimPage token={claimToken}/>
  if (customerSlug) return <CustomerPortal slug={customerSlug}/>
  if (user === undefined) return <div className="loading full">Loading...</div>
  if (!user) return <Login onLogin={setUser} />
  const logout = async () => { await api.post('/logout'); setUser(null) }
  return <Shell user={user} onLogout={logout}/>
}
