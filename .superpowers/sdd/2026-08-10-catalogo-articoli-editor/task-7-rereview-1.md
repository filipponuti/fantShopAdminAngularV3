# Task 7 Re-review — Fix Round 1

## Finding

**ADDRESSED**

Fix round 1 restores keyboard-operable section selection as requested. Each section row now exposes button semantics (`role="button"`, `tabindex="0"`, `aria-pressed`), activates selection on Enter and Space via `onSectionKeydown`, prevents Space from scrolling the page, stops keyboard propagation from nested drag handles/switches/action controls so they do not trigger section selection, and adds a visible `:focus-visible` outline. A unit test covers Enter/Space selection and Space default prevention. Keyboard-only users can again focus a section, set `selectedSectionIndex`, and add searched SKUs.

Evidence: `fant-cataloghi-articoli.component.html:129-138,147,160,210`; `fant-cataloghi-articoli.component.ts:251-258`; `fant-cataloghi-articoli.component.scss:101-104`; `fant-cataloghi-articoli.component.spec.ts:277-288`.

## New Critical/Important breakage

None.

## Verdict

All findings addressed.
