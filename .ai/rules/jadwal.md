---
paths:
  - 'resources/js/pages/k3/jadwal/**'
---

# Jadwal

## Jadwal K3 on phones opens read-only
On phones, jadwal pages open in view mode, showing the selected date's schedule. Input happens on desktop, or on the phone after the "Ubah Jadwal" button (MobileModeBar). Implement this with page state `mobileEditing` passed as `editing` / `onEditingChange` to MobileTimelineForm or MobileMatrixForm. Gate the header write actions (Tambah / Tandai / Simpan) with `can_write && (!compact || mobileEditing || dirty)`: Simpan stays reachable while there are unsaved changes, and desktop is unaffected.
