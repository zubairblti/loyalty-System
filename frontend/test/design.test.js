import test from 'node:test'
import assert from 'node:assert/strict'
import { contrastText, notificationBadge } from '../src/design.js'

test('contrast text selects readable light and dark values', () => {
  assert.equal(contrastText('#123e63'), '#ffffff')
  assert.equal(contrastText('#f5f7f9'), '#17211d')
})

test('notification badge preserves exact totals and caps large values', () => {
  assert.equal(notificationBadge(0), null)
  assert.equal(notificationBadge(25), '25')
  assert.equal(notificationBadge(120), '99+')
})
