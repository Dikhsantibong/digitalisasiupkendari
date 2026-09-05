# Corporate Enterprise UI Design System

## 1. Design Direction

Gunakan pendekatan **Corporate Enterprise** dengan karakter visual:

- Profesional
- Formal
- Terpercaya
- Efisien
- Presisi
- Data-oriented
- Mature
- Konsisten

Antarmuka harus terasa seperti **aplikasi profesional kelas enterprise**, bukan website marketing, dashboard startup, atau UI yang terlihat seperti hasil template AI.

Prioritas utama desain:

> **Clarity → Efficiency → Consistency → Trust → Aesthetics**

Visual yang menarik tetap diperlukan, tetapi **fungsi dan keterbacaan informasi selalu lebih penting daripada dekorasi**.

---

# 2. Core Design Principles

## 2.1 Functional First

Setiap elemen UI harus mempunyai fungsi yang jelas.

Gunakan visual untuk:

- Menunjukkan hierarki informasi
- Menunjukkan status
- Membantu navigasi
- Menjelaskan hubungan antar data
- Membantu pengguna mengambil keputusan
- Memberikan feedback terhadap aksi pengguna

Hindari elemen yang hanya digunakan sebagai dekorasi.

Jangan menambahkan:

- Ornamen abstrak
- Blob
- Gradient dekoratif
- Ilustrasi yang tidak memiliki fungsi
- Pattern background berlebihan
- Floating decoration
- Glow effect
- Excessive glassmorphism

---

## 2.2 Information First

Aplikasi harus memprioritaskan informasi yang benar-benar dibutuhkan pengguna.

Informasi penting harus mudah ditemukan tanpa pengguna harus membuka terlalu banyak halaman.

Prioritaskan:

- Informasi utama
- Status
- Metadata
- Progress
- Timeline
- Aktivitas
- Riwayat
- Data numerik
- Action yang membutuhkan perhatian

Gunakan ukuran, weight, spacing, alignment, dan semantic color untuk membedakan tingkat kepentingan informasi.

Jangan menggunakan dekorasi untuk menarik perhatian jika hierarchy typography dan layout sudah cukup.

---

## 2.3 Enterprise Density

Gunakan **medium-to-high information density**.

Jangan menggunakan whitespace secara berlebihan seperti pada landing page.

Namun, jangan membuat tampilan terlalu padat hingga sulit dibaca.

Whitespace digunakan untuk **mengelompokkan dan memisahkan informasi**, bukan sekadar membuat halaman terlihat kosong.

Struktur umum:

```text
Page
 ├── Header
 ├── Summary / Context
 ├── Filter / Toolbar
 ├── Primary Content
 └── Supporting Information
```

---

# 3. Visual Language

## 3.1 Overall Appearance

Karakter visual:

- Flat
- Clean
- Structured
- Precise
- Subtle
- Professional

Gunakan border dan surface separation sebagai mekanisme utama untuk membedakan area.

Jangan mengandalkan shadow besar untuk membuat setiap elemen terlihat seperti floating card.

---

## 3.2 Surface Hierarchy

Gunakan beberapa tingkat surface yang konsisten.

### Level 0 — Application Background

Background utama aplikasi.

Contoh:

```text
#F1F7FC
```

### Level 1 — Content Surface

Digunakan untuk:

- Table
- Form
- Panel
- Card
- Detail section

Contoh:

```text
#FFFFFF
```

### Level 2 — Secondary Surface

Digunakan secara terbatas untuk:

- Toolbar
- Filter area
- Selected row
- Secondary information
- Highlight area

Contoh:

```text
#EAF4FB
```

Jangan membuat terlalu banyak variasi surface.

---

# 4. Color System

Gunakan warna secara sistematis.

Identitas warna aplikasi mengikuti **brand PLN Nusantara Power**: dominan
**biru muda + putih**, dengan **kuning pisang** (warna kotak logo PLN) sebagai
aksen yang dipakai **sangat terbatas** (hanya untuk warning / highlight
sesekali), bukan warna utama.

Struktur warna:

```text
Primary   → PLN Blue
Neutral   → Blue-tinted grey + white
Success   → Green
Warning   → Banana Yellow (aksen logo PLN, terbatas)
Danger    → Red
Info      → PLN Blue
```

Gunakan satu warna primary sebagai identitas utama interface.

Jangan menggunakan banyak warna aksen dalam satu halaman. Kuning pisang **tidak**
digunakan sebagai background besar atau warna dominan — cukup untuk status
warning dan penekanan kecil.

> **Catatan tema:** Aplikasi dikunci pada tema terang (light). Fitur pengubahan
> tema (Appearance) di halaman Settings dinonaktifkan, sehingga tampilan selalu
> konsisten biru–putih untuk seluruh pengguna. Dark mode tidak digunakan.

---

## 4.1 Primary Color

Gunakan satu warna korporat utama untuk:

- Primary button
- Active navigation
- Selected state
- Link
- Important action
- Focus indicator
- Highlight

Arah warna:

```text
PLN Blue (Light/Corporate Blue)
```

Contoh:

```text
Primary:       #0C7DBB   (PLN Blue)
Primary Hover: #0A6BA1
Primary Light: #E0F0FA   (accent / selected background)
Sidebar Blue:  #0B6AA2   (sidebar solid, teks putih)
```

Sidebar menggunakan warna PLN Blue solid dengan teks putih; active state memakai
lapisan putih transparan. **Tanpa gradient.**

---

## 4.2 Neutral Colors

Gunakan neutral colors (dengan sedikit rona biru) untuk mayoritas interface.

Contoh:

```text
Text Primary:   #16323F
Text Secondary: #40566B
Text Muted:     #5B7280

Border:         #CFE1EE
Divider:        #DCEAF4

Background:     #F1F7FC   (Level 0)
Surface:        #FFFFFF   (Level 1)
Secondary:      #EAF4FB   (Level 2)
```

Hindari pure black untuk sebagian besar teks.

---

## 4.3 Accent — Banana Yellow

Kuning pisang (warna kotak logo PLN) hanya dipakai terbatas:

```text
Warning / Accent: #F5C400
Warning Text:     #7A5300
```

Gunakan untuk status warning, penekanan kecil, atau satu seri pada chart. Jangan
jadikan warna utama halaman.

---

# 5. Semantic Color

Warna harus memiliki makna yang konsisten.

| Semantic | Usage |
|---|---|
| Neutral | Informasi umum, inactive, pending |
| Blue | Informasi, active, processing |
| Green | Success, completed, valid |
| Amber | Warning, attention, pending action |
| Red | Error, rejected, destructive action |

Warna semantic harus digunakan secara konsisten di seluruh aplikasi.

Jangan menggunakan warna hanya karena terlihat menarik.

---

## 5.1 Status

Status harus menggunakan visual yang sederhana dan mudah dipahami.

Contoh:

```text
[ Draft ]
[ Pending ]
[ In Progress ]
[ Completed ]
[ Rejected ]
```

Gunakan kombinasi:

```text
Soft background + dark text
```

Hindari:

```text
Bright background + white text
```

kecuali untuk kondisi yang membutuhkan emphasis tinggi.

Status badge harus tetap **subtle dan corporate**.

---

# 6. Typography

Gunakan font sans-serif profesional.

Prioritas:

1. Inter
2. IBM Plex Sans
3. Söhne
4. System Sans equivalent

Typography harus memiliki hierarchy yang jelas.

---

## 6.1 Typography Hierarchy

### Page Title

```text
24–28px
Weight: 600–700
```

### Section Title

```text
16–18px
Weight: 600
```

### Body

```text
14px
Weight: 400
```

### Secondary Text

```text
13px
Weight: 400
```

### Table Text

```text
13–14px
```

### Important Numbers

```text
20–28px
Weight: 600–700
```

Gunakan **tabular numerals** untuk angka yang ditampilkan dalam tabel atau data summary.

---

# 7. Spacing System

Gunakan spacing system yang konsisten.

Basis:

```text
4px
```

Recommended values:

```text
4
8
12
16
20
24
32
40
48
```

Gunakan:

```text
16–24px
```

untuk spacing antar section utama.

Hindari spacing random kecuali memang diperlukan oleh layout.

---

# 8. Border & Radius

Gunakan border sebagai struktur visual utama.

Recommended:

```text
1px solid #D9DEE5
```

Border harus subtle.

### Border Radius

Gunakan radius kecil hingga medium:

```text
4px
6px
8px
```

Default yang disarankan:

```text
6px
```

Hindari penggunaan rounded corner yang terlalu besar.

Aplikasi enterprise harus terasa **structured**, bukan playful.

---

# 9. Shadow

Shadow digunakan secara minimal.

Default component:

```text
No shadow
```

atau shadow yang sangat subtle.

Shadow hanya diperlukan untuk:

- Dropdown
- Popover
- Modal
- Floating panel
- Temporary overlay

Jangan memberikan shadow pada setiap card atau section.

---

# 10. Layout System

Gunakan layout berbasis grid.

Struktur umum:

```text
┌──────────────────────────────────────────────┐
│ Header                                       │
├──────────────┬───────────────────────────────┤
│              │                               │
│ Sidebar      │ Main Content                  │
│              │                               │
│              │                               │
└──────────────┴───────────────────────────────┘
```

Layout harus mengutamakan:

- Alignment
- Consistent spacing
- Clear grouping
- Predictable navigation
- Efficient use of space

---

# 11. Sidebar

Sidebar harus:

- Fixed
- Compact
- Konsisten
- Mudah dipindai
- Tidak dekoratif

Sidebar dapat berisi:

- Brand / application identity
- Main navigation
- Navigation groups
- Active state
- User/account area

Active menu menggunakan:

- Primary color
- Subtle background
- Optional visual indicator

Jangan menggunakan gradient untuk active navigation.

---

# 12. Top Header

Header digunakan untuk:

- Page context
- Breadcrumb
- Notification
- User information
- Global action
- Search jika diperlukan

Header harus tetap sederhana.

Jangan memenuhi header dengan banyak icon tanpa fungsi.

---

# 13. Breadcrumb

Gunakan breadcrumb pada halaman yang memiliki struktur navigasi bertingkat.

Contoh:

```text
Home / Category / Current Page
```

Current page dapat menggunakan font weight yang lebih tinggi.

Gunakan typography yang subtle untuk parent navigation.

---

# 14. Dashboard

Dashboard harus berorientasi pada **informasi dan action**, bukan dekorasi.

Prioritaskan:

1. Summary
2. Important information
3. Action required
4. Main data
5. Supporting visualization

Struktur umum:

```text
Page Header

[ Summary ] [ Summary ] [ Summary ] [ Summary ]

Filter / Context

Main Data

Supporting Information
```

Jangan memenuhi dashboard dengan chart hanya agar terlihat modern.

---

# 15. Summary Cards

Summary card digunakan untuk informasi penting yang dapat dipahami secara cepat.

Contoh generik:

```text
TOTAL
128

Updated today
```

atau:

```text
ACTIVE
32

+12% this month
```

Card harus sederhana.

Gunakan:

- Number
- Label
- Optional supporting information
- Optional trend

Hindari:

- Icon besar dekoratif
- Gradient
- Illustration
- Excessive shadow
- Banyak warna dalam satu card

---

# 16. Table Design

Table digunakan ketika informasi membutuhkan perbandingan antar baris dan kolom.

Prioritaskan:

- Readability
- Scanability
- Alignment
- Consistent row height
- Clear headers

Struktur:

```text
┌─────────────────────────────────────────────────────┐
│ Search / Filter / Toolbar                           │
├─────────────────────────────────────────────────────┤
│ Column │ Column │ Column │ Status │ Action          │
├─────────────────────────────────────────────────────┤
│ Data   │ Data   │ Data   │ Badge  │ ...             │
│ Data   │ Data   │ Data   │ Badge  │ ...             │
└─────────────────────────────────────────────────────┘
```

---

## 16.1 Table Rules

Gunakan:

- Header yang jelas
- Divider tipis
- Row height konsisten
- Alignment konsisten
- Hover state subtle
- Status badge jika diperlukan
- Pagination jika diperlukan
- Sorting jika relevan
- Filtering jika relevan

Jangan menggunakan zebra striping secara berlebihan.

---

## 16.2 Alignment

Gunakan:

```text
Text      → Left
Date      → Left / Center
Status    → Left
Number    → Right
Currency  → Right
Action    → Center / Right
```

Data numerik harus menggunakan alignment kanan agar mudah dibandingkan.

---

# 17. Search & Filter

Filter harus mudah ditemukan dan digunakan.

Contoh:

```text
[ Search __________________ ]

[ Status ▼ ] [ Category ▼ ] [ Date ▼ ] [ More Filters ]
```

Gunakan filter berdasarkan kebutuhan pengguna.

Advanced filter dapat ditempatkan dalam:

- Filter drawer
- Popover
- Advanced filter panel

Jangan membuat filter tersebar di berbagai bagian halaman.

---

# 18. Form Design

Form harus terasa terstruktur dan profesional.

Gunakan pola:

```text
Label
Input
Helper text
Error message
```

Label selalu berada di atas field.

Contoh:

```text
Name *

[____________________________]

Optional helper text
```

Jangan menggunakan placeholder sebagai pengganti label.

---

## 18.1 Form Rules

Gunakan:

- Consistent field height
- Clear label
- Required indicator
- Helper text bila diperlukan
- Validation
- Error message
- Disabled state
- Read-only state

Field yang berhubungan harus dikelompokkan secara visual.

---

# 19. Button System

Gunakan hierarchy tombol yang konsisten.

### Primary

Untuk action utama:

```text
Save
Submit
Continue
Confirm
```

### Secondary

Untuk action pendukung:

```text
Cancel
Back
Filter
Reset
```

### Destructive

Untuk action berisiko:

```text
Delete
Remove
Reject
Cancel Process
```

Destructive action harus menggunakan semantic danger color.

Jangan menggunakan banyak primary button dalam satu area.

Idealnya hanya terdapat **satu primary action utama** dalam satu konteks.

---

# 20. Modal & Confirmation

Modal digunakan ketika pengguna perlu:

- Mengambil keputusan
- Mengonfirmasi action
- Mengisi informasi tambahan
- Menyelesaikan task tertentu

Contoh generik:

```text
Confirm Action

Are you sure you want to continue?

[ Cancel ] [ Confirm ]
```

Gunakan modal secara selektif.

Jangan menggunakan modal untuk informasi sederhana yang dapat ditampilkan langsung pada halaman.

---

# 21. Detail Page

Halaman detail harus memprioritaskan informasi utama.

Struktur umum:

```text
Breadcrumb

Page Header
├── Title
├── Status
├── Metadata
└── Primary Action

Summary

Information

Progress / Timeline

Related Data

Activity / History
```

Tidak semua section harus digunakan.

Gunakan hanya section yang relevan dengan konteks halaman.

---

# 22. Timeline & Process

Gunakan timeline ketika sebuah aktivitas memiliki urutan atau progress.

Contoh generik:

```text
● Step 1
│
● Step 2
│
● Step 3
│
○ Step 4
│
○ Step 5
```

Gunakan state:

- Completed
- Current
- Upcoming
- Failed
- Skipped

State harus dapat dipahami secara visual tanpa bergantung hanya pada warna.

---

# 23. Document & File Interface

Jika aplikasi menangani file, gunakan layout yang sederhana dan informatif.

Contoh:

```text
File Name             Status       Updated       Action
────────────────────────────────────────────────────────
Document A             Ready        Today         View
Document B             Pending      Yesterday     Open
Document C             Failed       Today         Retry
```

Hindari penggunaan icon file besar hanya sebagai dekorasi.

---

# 24. Empty State

Empty state harus informatif.

Contoh:

```text
No data available

There is currently no information to display.

[ Refresh ]
```

Jika terdapat filter:

```text
No results found

Try adjusting your search or filter.

[ Reset Filter ]
```

Jangan menggunakan ilustrasi besar atau karakter kartun.

---

# 25. Loading State

Gunakan:

- Skeleton
- Spinner pada action
- Progress indicator

Prioritaskan skeleton untuk content area yang membutuhkan waktu loading.

Loading state harus memberikan indikasi bahwa sistem sedang bekerja.

Jangan menggunakan animation yang terlalu dekoratif.

---

# 26. Error State

Error harus:

- Jelas
- Singkat
- Tidak menyalahkan pengguna
- Memberikan solusi jika memungkinkan

Contoh:

```text
Unable to load data

Something went wrong while loading this information.

[ Try Again ]
```

Gunakan semantic danger color secara restrained.

---

# 27. Notification & Feedback

Gunakan feedback berdasarkan tingkat kepentingan.

### Success

Untuk action yang berhasil.

### Warning

Untuk kondisi yang membutuhkan perhatian.

### Error

Untuk kegagalan atau validation issue.

### Info

Untuk informasi tambahan.

Gunakan:

- Toast untuk feedback singkat
- Alert untuk informasi yang perlu tetap terlihat
- Inline validation untuk error pada form

---

# 28. Iconography

Gunakan satu icon library profesional.

Recommended:

- Lucide
- Feather
- Material Symbols

Pilih satu library dan gunakan secara konsisten.

Icon harus memiliki:

- Consistent stroke weight
- Consistent size
- Consistent visual style

Jangan menggunakan emoji sebagai icon UI.

Icon harus membantu pengguna memahami fungsi.

---

# 29. Responsive Behavior

Desktop dapat menjadi primary environment untuk aplikasi enterprise, tetapi layout tetap harus responsive.

### Desktop

```text
Sidebar + Header + Main Content
```

### Tablet

```text
Collapsible Sidebar + Main Content
```

### Mobile

Prioritaskan:

- Essential information
- Vertical layout
- Scrollable complex tables
- Stacked forms
- Bottom sheet / drawer untuk filter

Jangan hanya mengecilkan desktop UI.

---

# 30. Interaction States

Setiap interactive component harus memiliki state yang jelas:

```text
Default
Hover
Focus
Active
Selected
Disabled
Loading
Error
Success
```

State tidak boleh hanya bergantung pada perubahan warna.

Gunakan kombinasi:

- Color
- Border
- Background
- Typography
- Icon
- Position

---

# 31. Accessibility

UI harus mempertimbangkan accessibility.

Minimal:

- Contrast yang cukup
- Focus state yang terlihat
- Keyboard navigation
- Label form yang jelas
- Error message yang informatif
- Interactive element memiliki accessible name
- Jangan menggunakan warna sebagai satu-satunya indikator

Contoh:

Jangan hanya:

```text
●
```

Gunakan:

```text
● Completed
```

---

# 32. Data Visualization

Gunakan chart hanya jika membantu pengguna memahami informasi.

Prioritaskan:

- Trend
- Comparison
- Distribution
- Progress
- Performance
- Summary

Hindari:

- 3D chart
- Excessive gradients
- Decorative charts
- Chart tanpa insight
- Terlalu banyak jenis chart dalam satu halaman

---

# 33. Anti AI-Generated UI Rules

Hasil desain **tidak boleh terlihat seperti template AI generik**.

### Jangan gunakan:

#### Generic Gradient

```text
Purple → Blue
Pink → Orange
```

sebagai background utama.

#### Excessive Rounded Cards

Jangan membuat setiap section:

```text
rounded-xl
shadow-lg
```

#### Bento Grid

Jangan menggunakan bento-grid hanya karena terlihat modern.

Gunakan grid berdasarkan kebutuhan informasi.

#### Huge Decorative Icons

Jangan menggunakan icon besar hanya untuk menghias card.

#### Excessive Whitespace

Aplikasi kerja membutuhkan information density yang baik.

#### Excessive Color

Gunakan warna berdasarkan semantic meaning.

#### Glassmorphism

Hindari:

```text
blur
transparency
glass panels
glow
```

kecuali memiliki alasan fungsional yang jelas.

#### Marketing Layout

Jangan membuat halaman aplikasi seperti:

```text
Hero section
Huge headline
Large illustration
Floating cards
Decorative graphics
```

---

# 34. Component Consistency

Komponen yang sama harus terlihat sama di seluruh aplikasi.

Komponen yang harus konsisten:

- Button
- Input
- Select
- Badge
- Table
- Card
- Modal
- Tabs
- Pagination
- Tooltip
- Alert
- Toast
- Dropdown
- Navigation

Jangan membuat variasi visual baru tanpa alasan yang jelas.

---

# 35. Page Structure Standard

Gunakan struktur umum berikut bila relevan:

```text
┌─────────────────────────────────────────────────────┐
│ Breadcrumb                                           │
│                                                     │
│ Page Title                         Primary Action   │
│ Description / Metadata                              │
├─────────────────────────────────────────────────────┤
│ Summary / Context                                   │
├─────────────────────────────────────────────────────┤
│ Search / Filter / Toolbar                          │
├─────────────────────────────────────────────────────┤
│                                                     │
│ Main Content                                        │
│                                                     │
├─────────────────────────────────────────────────────┤
│ Pagination / Supporting Information                │
└─────────────────────────────────────────────────────┘
```

Struktur tidak harus diterapkan secara literal pada setiap halaman.

Gunakan sesuai kebutuhan informasi.

---

# 36. Visual Priority

Gunakan hierarchy:

```text
Level 1
Primary information

Level 2
Supporting information

Level 3
Metadata

Level 4
Utility / Secondary actions
```

Contoh generik:

```text
MAIN TITLE                         ← Level 1

Primary Value / Information       ← Level 1

Current Status                    ← Level 2

Updated: Today                    ← Level 3
Owner: User                       ← Level 3

Edit · More                       ← Level 4
```

---

# 37. Design Token Philosophy

Jika design system diimplementasikan menggunakan code, gunakan design tokens.

Contoh:

```text
color.primary
color.background
color.surface
color.border
color.text.primary
color.text.secondary
color.success
color.warning
color.danger

spacing.xs
spacing.sm
spacing.md
spacing.lg
spacing.xl

radius.sm
radius.md

font.size.sm
font.size.md
font.size.lg
font.size.xl
```

Jangan hardcode nilai visual secara acak pada setiap component.

---

# 38. Quality Checklist

Sebelum sebuah halaman dianggap selesai:

- [ ] Hierarki visual jelas
- [ ] Tidak ada dekorasi yang tidak memiliki fungsi
- [ ] Typography konsisten
- [ ] Spacing mengikuti sistem
- [ ] Button hierarchy jelas
- [ ] Semantic color digunakan dengan benar
- [ ] Table mudah dipindai
- [ ] Data numerik memiliki alignment yang benar
- [ ] Form memiliki label yang jelas
- [ ] Error state tersedia
- [ ] Loading state tersedia
- [ ] Empty state tersedia
- [ ] Hover/focus/disabled state tersedia
- [ ] Responsive behavior dipikirkan
- [ ] Tidak menggunakan emoji sebagai icon
- [ ] Tidak menggunakan gradient generik
- [ ] Tidak menggunakan shadow berlebihan
- [ ] Tidak menggunakan rounded corner berlebihan
- [ ] Tidak terlihat seperti landing page
- [ ] Tidak terlihat seperti template AI-generated
- [ ] Visual terasa profesional dan matang
- [ ] Komponen konsisten dengan halaman lain
- [ ] Setiap elemen memiliki tujuan yang jelas

---

# 39. Final Design Philosophy

Desain harus memberikan kesan:

> **Professional. Precise. Structured. Trustworthy.**

Bukan:

> **Colorful. Decorative. Playful. Generic. AI-generated.**

Setiap keputusan desain harus menjawab minimal salah satu pertanyaan berikut:

1. Apakah ini membuat informasi lebih mudah dipahami?
2. Apakah ini mempercepat pekerjaan pengguna?
3. Apakah ini memperjelas status atau kondisi?
4. Apakah ini meningkatkan konsistensi antar halaman?
5. Apakah ini membantu pengguna mengambil keputusan?
6. Apakah ini meningkatkan usability?

Jika jawabannya tidak, elemen tersebut kemungkinan tidak diperlukan.

---

# 40. Final Target

Target visual akhir:

**Professional + Dense + Precise + Clear + Consistent + Trustworthy + Modern**

Bukan:

**Colorful + Decorative + Spacious + Playful + Generic + AI-generated**