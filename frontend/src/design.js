export function contrastText(hex) {
  const value = (hex || '#123e63').replace('#', '')
  const [r, g, b] = [0, 2, 4].map(index => Number.parseInt(value.slice(index, index + 2), 16))
  return ((r * 299 + g * 587 + b * 114) / 1000) > 150 ? '#17211d' : '#ffffff'
}

export function notificationBadge(count) {
  if (!count) return null
  return count > 99 ? '99+' : String(count)
}
