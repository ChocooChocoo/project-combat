import assert from 'node:assert/strict'
import test from 'node:test'
import { chosenSuffix, relationshipChoices } from '../src/lib/validation.ts'

// Both helpers guard bugs the browser walkthrough found (see PLAN.md, Phase 1).

test('chosenSuffix leaves the "None" placeholder out of the request', () => {
  // The Select writes its placeholder value into the form, and the API refuses anything but a real suffix.
  assert.equal(chosenSuffix('none'), undefined)
  assert.equal(chosenSuffix(''), undefined)
  assert.equal(chosenSuffix(null), undefined)
  assert.equal(chosenSuffix('none', 'none'), undefined)
})

test('chosenSuffix keeps a real suffix', () => {
  assert.equal(chosenSuffix('Jr.'), 'Jr.')
  assert.equal(chosenSuffix('III'), 'III')
})

test('relationshipChoices offers an older value as an old entry above the list', () => {
  const choices = relationshipChoices(['Parent', 'Spouse'], 'Mother')
  assert.deepEqual(choices, [
    { value: 'Mother', label: 'Mother (old entry)' },
    { value: 'Parent', label: 'Parent' },
    { value: 'Spouse', label: 'Spouse' },
  ])
})

test('relationshipChoices is only the list when the value is current or empty', () => {
  assert.deepEqual(relationshipChoices(['Parent', 'Spouse'], 'Parent'), [
    { value: 'Parent', label: 'Parent' },
    { value: 'Spouse', label: 'Spouse' },
  ])
  assert.deepEqual(relationshipChoices(['Parent'], ''), [{ value: 'Parent', label: 'Parent' }])
})
