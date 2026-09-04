import { beforeEach, describe, expect, it } from 'vitest'

import './validation.js'

describe('ArbeitszeitCheckValidation break-split (AZG §11)', () => {
  const V = () => window.ArbeitszeitCheckValidation

  beforeEach(() => {
    window.ArbeitszeitCheck = {
      complianceParams: {
        breakTiers: [{ afterHours: 6, breakMinutes: 30 }],
        minBreakMinutes: 10,
        allowedBreakSplitPatterns: [
          [15, 15],
          [10, 10, 10],
        ],
        lawLabels: { breaks: 'AZG §11' },
      },
      l10n: {
        complianceBreakSplitInvalid:
          'Breaks must be one continuous block of the required length, or 2×15 minutes, or 3×10 minutes (AZG §11)',
        complianceBreakNotMet: 'Break requirement not met (AZG §11)',
      },
    }
  })

  it('rejects illegal 20+10 split that sums to 30', () => {
    expect(V().meetsBreakSplitRequirement([20, 10], 30, [
      [15, 15],
      [10, 10, 10],
    ])).toBe(false)

    const result = V().evaluateBreakCompliance([20, 10], 6.5)
    expect(result.ok).toBe(false)
    expect(result.splitInvalid).toBe(true)
    expect(result.message).toContain('2×15')
  })

  it('does not require a break at exactly 6 hours (mehr als sechs Stunden)', () => {
    const result = V().evaluateBreakCompliance([], 6)
    expect(result.ok).toBe(true)
    expect(result.requiredMinutes).toBe(0)
  })

  it('accepts continuous 30, 15+15, and 10+10+10', () => {
    const patterns = [
      [15, 15],
      [10, 10, 10],
    ]
    expect(V().meetsBreakSplitRequirement([30], 30, patterns)).toBe(true)
    expect(V().meetsBreakSplitRequirement([15, 15], 30, patterns)).toBe(true)
    expect(V().meetsBreakSplitRequirement([10, 10, 10], 30, patterns)).toBe(true)
    expect(V().evaluateBreakCompliance([15, 15], 6.5).ok).toBe(true)
  })

  it('sum-only regimes ignore split shape when total is enough', () => {
    window.ArbeitszeitCheck.complianceParams.allowedBreakSplitPatterns = null
    window.ArbeitszeitCheck.complianceParams.breakTiers = [
      { afterHours: 6, breakMinutes: 30 },
      { afterHours: 9, breakMinutes: 45 },
    ]
    expect(V().meetsBreakSplitRequirement([20, 10], 30, null)).toBe(true)
    expect(V().evaluateBreakCompliance([20, 10], 6.5).ok).toBe(true)
    expect(V().calculateRequiredBreakMinutes(9)).toBe(30)
    expect(V().calculateRequiredBreakMinutes(9 + 1 / 60)).toBe(45)
  })

  it('reports insufficient total without splitInvalid flag', () => {
    const result = V().evaluateBreakCompliance([10], 6.5)
    expect(result.ok).toBe(false)
    expect(result.splitInvalid).toBe(false)
    expect(result.requiredMinutes).toBe(30)
  })
})
