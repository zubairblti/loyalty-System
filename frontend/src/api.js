import axios from 'axios'

export const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' },
})

export async function login(email, password) {
  await prepareCsrf()
  return (await api.post('/login', { email, password })).data
}

export async function prepareCsrf() {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}
