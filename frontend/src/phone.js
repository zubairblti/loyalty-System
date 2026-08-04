export function phoneDigits(value = '') {
  let digits = String(value).replace(/\D/g, '')
  if (digits.startsWith('0092')) digits = digits.slice(4)
  else if (digits.startsWith('92')) digits = digits.slice(2)
  if (digits.length === 10 && digits.startsWith('3')) digits = `0${digits}`
  return digits.slice(0, 11)
}

export function formatPhone(value = '') {
  const digits = phoneDigits(value)
  if (!digits) return ''
  if (digits.length <= 4) return `(${digits}`
  return `(${digits.slice(0, 4)})${digits.slice(4)}`
}
