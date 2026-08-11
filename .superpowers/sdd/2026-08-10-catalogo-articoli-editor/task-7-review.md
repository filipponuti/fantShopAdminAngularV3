# Task 7 Review — Main toggle/riordino/Salva

- Spec compliance: ✅
- Task quality: Changes requested
- Critical/Important:
  - **[P2] Restore keyboard-operable section selection — `default/src/app/pages/fant/fant-cataloghi-articoli/fant-cataloghi-articoli.component.html:129-134`.** Task 7 replaces the previously focusable section `<button>` with a plain `<section>` that only has a click handler. Keyboard-only users can no longer select a section, so they cannot establish `selectedSectionIndex` and then add searched SKUs. Keep the non-nested-button structure, but give the selectable surface button semantics and Enter/Space handling (or provide a dedicated selection button).
- Minor:
  - The new specs instantiate the component class directly, so they verify reorder/save methods but do not compile the template or exercise actual CDK drag-drop wiring. A small TestBed/template interaction test would cover the highest residual integration risk.
  - Controls outside the footer remain editable while `saving` is true. A change made after the request is dispatched can remain visible under “Catalogo salvato.” without being part of that save; disabling edits during save or tracking post-submit dirty state would avoid misleading feedback.

Verification: focused component suite passed, 15/15.
