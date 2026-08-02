import axios from 'axios'
import { toast } from './toast'
import { beginLoading, endLoading } from './loading'

export const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

const successMessages = [
  [/\/register\/verify$/, 'Email verified. Your business account is ready.'],
  [/\/register\/resend$/, 'A new verification code has been sent.'],
  [/\/register$/, 'Verification code sent to your email.'],
  [/\/forgot-password$/, 'Password reset code sent.'],
  [/\/reset-password$/, 'Password updated successfully.'],
  [/\/subscription\/payments$/, 'Payment submitted for approval.'],
  [/\/pos\/sales$/, 'Sale completed and points queued.'],
  [/\/pos\/terminals$/, 'POS terminal created.'],
  [/\/qr-codes$/, 'QR code created.'],
  [/\/domains$/, 'Domain added for verification.'],
  [/\/admin\/plan$/, 'Subscription plan saved.'],
  [/\/review$/, 'Payment review saved.'],
  [/\/cash-payment$/, 'Cash subscription activated.'],
  [/\/profile\/phone\/verify$/, 'Mobile number verified.'],
  [/\/profile$/, 'Profile updated.'],
]

api.interceptors.request.use(config => {
  if (config.showLoader !== false) {
    config.loaderStarted = true
    beginLoading()
  }
  return config
}, error => {
  if (error.config?.loaderStarted) endLoading()
  return Promise.reject(error)
})

api.interceptors.response.use(response => {
  if (response.config.loaderStarted) endLoading()
  if (['post', 'put', 'patch'].includes(response.config.method)) {
    const match = successMessages.find(([pattern]) => pattern.test(response.config.url || ''))
    if (match) toast(match[1], 'success')
  }
  return response
}, error => {
  if (error.config?.loaderStarted) endLoading()
  const url = error.config?.url || ''
  const silent = error.response?.status === 401 && (url === '/me' || url.endsWith('/dashboard'))
  if (!silent) {
    const validation = Object.values(error.response?.data?.errors || {})[0]?.[0]
    toast(validation || error.response?.data?.message || 'The request could not be completed.', 'error')
  }
  return Promise.reject(error)
})

export async function login(email, password) {
  await prepareCsrf()
  return (await api.post('/login', { email, password })).data
}

export async function prepareCsrf() {
  beginLoading()
  try {
    await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
  } finally {
    endLoading()
  }
}
