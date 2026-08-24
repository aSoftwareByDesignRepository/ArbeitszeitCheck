import { beforeEach, describe, expect, it } from 'vitest'

import './validation.js'

describe('ArbeitszeitCheckValidation exclusive DACH break tiers', () => {
  const V = () => window.ArbeitszeitCheckValidation

  beforeEach(() => {
    window.ArbeitszeitCheck = {
      complianceParams: {
        breakTiers: [
          { afterHours: 6, breakMinutes: 30 },
          { afterHours: 9, breakMinutes: 45 },
        ],
        minBreakMinutes: 15,
        allowedBreakSplitPatterns: null,
        lawLabels: { breaks: 'ArbZG §4' },
      },
    }
  })

  it('requires a break only after more than six / nine hours', () => {
    expect(V().calculateRequiredBreakMinutes(5.99)).toBe(0)
    expect(V().calculateRequiredBreakMinutes(6)).toBe(0)
    expect(V().calculateRequiredBreakMinutes(6 + 1 / 60)).toBe(30)
    expect(V().calculateRequiredBreakMinutes(9)).toBe(30)
    expect(V().calculateRequiredBreakMinutes(9 + 1 / 60)).toBe(45)
  })

  it('uses net working time, not the wall-clock span', () => {
    expect(V().netWorkingHours(6.5, 30)).toBe(6)
    expect(V().calculateRequiredBreakMinutes(V().netWorkingHours(6.5, 30))).toBe(0)
    expect(V().calculateRequiredBreakMinutes(V().netWorkingHours(6.5, 0))).toBe(30)
    expect(V().netWorkingHours(0, 30)).toBe(0)
    expect(V().netWorkingHours(-1, 0)).toBe(0)
  })

  it('treats Swiss 5.5h as exclusive', () => {
    window.ArbeitszeitCheck.complianceParams.breakTiers = [
      { afterHours: 5.5, breakMinutes: 15 },
      { afterHours: 7, breakMinutes: 30 },
      { afterHours: 9, breakMinutes: 60 },
    ]
    expect(V().calculateRequiredBreakMinutes(5.5)).toBe(0)
    expect(V().calculateRequiredBreakMinutes(5.5 + 1 / 60)).toBe(15)
    expect(V().calculateRequiredBreakMinutes(7)).toBe(15)
    expect(V().calculateRequiredBreakMinutes(7 + 1 / 60)).toBe(30)
  })
})
