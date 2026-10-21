# Interface interactions

Customer, authentication and staff layouts load `motion.css` last and `motion.js` before the existing interaction scripts. Motion is automatic; no preference control or stored setting is required.

Use the shared 160/260/360 ms scale for feedback, menus and larger surfaces; dialogs enter in 320 ms and exit in 200 ms. Keep movement small and finite. Avoid animation on every table row or product card as a page loads.

`AvelaMotion` provides dialog entry/exit, expandable menus, content reveals and state-change feedback. Reversed interactions cancel stale animation jobs. A bounded fallback keeps controls usable if animation completion is unavailable. Preserve native semantics, keyboard focus, validation and server authorization.

Loading feedback reflects a real pending operation. Never add an artificial wait, disable named form values, or send a second business request to produce an effect. Native select menus remain browser-controlled.

Run `node tests/motion.cjs` for animation lifecycle checks, alongside the affected PHP regressions and desktop/mobile browser tests.
