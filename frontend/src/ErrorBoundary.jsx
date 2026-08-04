import { Component } from 'react'
import { CircleAlert, RefreshCw } from 'lucide-react'

export default class ErrorBoundary extends Component {
  constructor(props) {
    super(props)
    this.state = { error: null, errorId: null }
  }

  static getDerivedStateFromError(error) {
    return { error, errorId: `UI-${Date.now().toString(36).toUpperCase()}` }
  }

  componentDidCatch(error, info) {
    console.error('LoyaltyOS interface error', { error, componentStack: info.componentStack, errorId: this.state.errorId })
  }

  render() {
    if (!this.state.error) return this.props.children

    return <main className="app-error-page">
      <section className="app-error-panel">
        <span className="app-error-icon"><CircleAlert size={25}/></span>
        <small>INTERFACE ERROR</small>
        <h1>This page could not be displayed</h1>
        <p>Your account data is safe. Reload the page to retry the operation.</p>
        {import.meta.env.DEV && <code>{this.state.error.message}</code>}
        <div className="app-error-actions">
          <button className="primary" onClick={() => window.location.reload()}><RefreshCw size={17}/>Try again</button>
          <button className="secondary" onClick={() => { window.location.href = window.location.pathname.startsWith('/admin') ? '/admin' : '/' }}>Return to sign in</button>
        </div>
        <small>Error reference: {this.state.errorId}</small>
      </section>
    </main>
  }
}
