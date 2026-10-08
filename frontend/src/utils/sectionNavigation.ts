type SectionPath = '/' | '/about' | '/shop'

const INTENT_DURATION_MS = 2000

let activeIntent: {
  path: SectionPath
  expiresAt: number
} | null = null

export const beginSectionNavigation = (path: SectionPath) => {
  activeIntent = {
    path,
    expiresAt: Date.now() + INTENT_DURATION_MS,
  }
}

export const canAutomaticallyNavigateTo = (
  path: Exclude<SectionPath, '/'>,
) => {
  if (!activeIntent) return true

  if (Date.now() >= activeIntent.expiresAt) {
    activeIntent = null

    return true
  }

  return activeIntent.path === path
}
