# Filomena backend architecture diagrams

These diagrams describe the historical public snapshot. They do not propose a redesign or certify current production readiness.

- [Light overview](architecture-light.svg) and [dark overview](architecture-dark.svg) are first-party, hand-authored SVG sources. Edit their semantic text and geometry directly; no generated-code dependency or external rendering service is required.
- The Mermaid block in the [repository README](../../README.md#architecture) remains the editable detailed logical view. Keep its responsibilities consistent with both SVGs.
- The overview uses left-to-right component groups, a dashed server-enforcement boundary and labeled operational views. It is a component map, not a timed sequence diagram or a physical deployment/exposure map.

## Visual maintenance

Use the shared documentation palette: light canvas `#F7F9FC`, cards `#FFFFFF`, text `#132238`; dark canvas `#0B1220`, cards `#111D2E`, text `#EDF3FF`. Teal identifies product responsibilities and violet marks server boundaries and operations. Color supplements labels; it never carries meaning alone.

Preserve system fonts, generous card spacing, orthogonal connectors, descriptive SVG `title`/`desc` and the README alternative text. Keep meaningful text contrast at least 4.5:1 and connectors at least 3:1 against the adjacent canvas. Both files must remain self-contained: no scripts, remote images, fonts, links, tracking or `foreignObject`.

After an edit, render both SVGs in a local browser and inspect the real output for clipping, overlaps, readable labels and arrow direction. Check README relative links and XML safety. Render the original Mermaid as well when its text changes. Source/XML checks alone are not visual verification. Runtime tests are unnecessary for a diagram-only change; do not start the historical application stack merely to preview documentation.
