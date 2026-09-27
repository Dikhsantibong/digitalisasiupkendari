---
paths:
  - 'resources/js/pages/har/jadwal/**'
---

# Har Jadwal

## Jadwal Pemeliharaan on phones opens read-only
As for K3, HAR jadwal pages on a phone open in view mode and switch to input with "Ubah Jadwal". The pages: Harian, P0–P5, Piket On Call, Patrol Check, Meeting, Pembuatan IK, and the jadwal HarLembarPage sheets. Pattern: page state `mobileEditing`, compact branch using MobileTimelineForm (tick cells; `initialDay`/`dayLabel` for monthly plans) or MobileDayValuesForm (code/number cells, e.g. P0–P5), and header write actions gated with `can_write && (!compact || mobileEditing || dirty)`. The phone shells are `har-project` (koordinator_pemeliharaan, menus/har-project.ts) and `har-pengusahaan` (tl_pemeliharaan, staf_pemeliharaan, built from PENGUSAHAAN_MENUS). HAR input and formulir pages are also in AppLayout's TABLE_CARD_PAGES, so leftover tables become cards on a phone.
