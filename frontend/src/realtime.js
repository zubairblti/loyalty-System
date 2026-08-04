import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

export function connectRealtime(businessId, onPointsUpdated) {
  if (!import.meta.env.VITE_PUSHER_APP_KEY) return () => {}

  window.Pusher = Pusher
  const echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'ap2',
    forceTLS: true,
    authEndpoint: '/broadcasting/auth',
    withCredentials: true,
  })
  echo.private(`businesses.${businessId}`).listen('.points.updated', onPointsUpdated)

  return () => echo.disconnect()
}

export function connectNotifications(userId, onNotification, audience = 'user', authEndpoint = '/broadcasting/auth') {
  if (!import.meta.env.VITE_PUSHER_APP_KEY) return () => {}
  window.Pusher = Pusher
  const echo = new Echo({ broadcaster: 'pusher', key: import.meta.env.VITE_PUSHER_APP_KEY, cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'ap2', forceTLS: true, authEndpoint, withCredentials: true })
  echo.private(`notifications.${audience}.${userId}`).listen('.notification.created', onNotification)
  return () => echo.disconnect()
}
