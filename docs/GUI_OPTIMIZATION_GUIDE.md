# 🎨 ACS GUI Optimization Guide - Soft UI Dashboard Pro

Guida completa per l'ottimizzazione della GUI del progetto ACS seguendo le best practices del template **Soft UI Dashboard Pro Laravel Livewire**.

---

## 📋 Panoramica

**Status**: ✅ CSS Utilities creato e caricato globalmente  
**File CSS**: `public/assets/css/soft-ui-enhancements.css`  
**Incluso in**: `resources/views/layouts/app.blade.php`

Le utility sono **già disponibili** in tutte le view del progetto!

---

## 🎯 Pattern di Ottimizzazione

### 1. Cards - Miglioramento Estetico

**PRIMA** (Base):
```html
<div class="card">
    <div class="card-body p-3">
        <!-- content -->
    </div>
</div>
```

**DOPO** (Ottimizzato):
```html
<div class="card shadow-lg-soft border-radius-xl card-hover">
    <div class="card-body p-3">
        <!-- content -->
    </div>
</div>
```

**Classi applicate**:
- `shadow-lg-soft` - Shadow sottile e morbido (0 15px 35px rgba)
- `border-radius-xl` - Border radius 1rem per angoli più soft
- `card-hover` - Hover effect con translateY(-5px)

**Varianti**:
- `shadow-xl-soft` - Shadow più pronunciato per cards importanti
- `card-hover-lift` - Hover con scale(1.02) per effetto lift
- `border-radius-lg` - Border radius 0.75rem (standard)

---

### 2. Stat Cards (Dashboard KPI)

**PRIMA**:
```html
<div class="col-xl-3 col-sm-6 mb-4">
    <div class="card">
        <div class="card-body p-3">
            <div class="icon icon-shape bg-gradient-primary shadow">
                <i class="ni ni-world"></i>
            </div>
            <h5>{{ $count }}</h5>
        </div>
    </div>
</div>
```

**DOPO** (Ottimizzato):
```html
<div class="col-xl-3 col-sm-6 mb-4 fade-in">
    <div class="card shadow-lg-soft border-radius-xl card-hover">
        <div class="card-body p-3">
            <div class="icon icon-shape icon-lg bg-gradient-primary shadow-primary rounded-circle">
                <i class="ni ni-world opacity-80"></i>
            </div>
            <h5 class="font-weight-bolder mt-3">{{ $count }}</h5>
            <p class="text-sm text-secondary mb-0">Description</p>
        </div>
    </div>
</div>
```

**Miglioramenti**:
- `fade-in` - Animazione ingresso
- `icon-lg` - Icon size 56px
- `shadow-primary` - Colored shadow matching gradient
- `rounded-circle` - Icon circular perfetto
- `opacity-80` - Icon opacity for soft look

---

### 3. Buttons - Gradient Enhancement

**PRIMA**:
```html
<button class="btn btn-primary">Click me</button>
<a href="#" class="btn btn-success">Action</a>
```

**DOPO** (Ottimizzato):
```html
<button class="btn bg-gradient-primary btn-hover-shadow">
    <i class="fas fa-plus me-2"></i>Click me
</button>
<a href="#" class="btn bg-gradient-success btn-gradient-hover">
    <i class="fas fa-check me-2"></i>Action
</a>
```

**Classi applicate**:
- `bg-gradient-primary` - Gradient background
- `btn-hover-shadow` - Elevazione su hover
- `btn-gradient-hover` - Opacity + translateY su hover
- `btn-icon-hover` - Scale icon su hover

**Icone**: Sempre con spacing `me-2` (margin-end)

---

### 4. Tables - Enhanced Hover & Spacing

**PRIMA**:
```html
<div class="table-responsive">
    <table class="table align-items-center mb-0">
        <thead>
            <tr>
                <th>Column</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Data</td>
            </tr>
        </tbody>
    </table>
</div>
```

**DOPO** (Ottimizzato):
```html
<div class="table-responsive border-radius-lg">
    <table class="table table-hover table-soft align-items-center mb-0">
        <thead>
            <tr>
                <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-70">
                    Column
                </th>
            </tr>
        </thead>
        <tbody>
            <tr class="cursor-pointer">
                <td class="text-sm font-weight-normal">Data</td>
            </tr>
        </tbody>
    </table>
</div>
```

**Miglioramenti**:
- `table-hover` - Hover background rgba(0,0,0,0.02)
- `table-soft` - Soft borders
- `opacity-70` - Header text opacity
- `cursor-pointer` - Pointer cursor su rows
- `border-radius-lg` - Round corners wrapper

---

### 5. Badges - Gradient Styles

**PRIMA**:
```html
<span class="badge bg-success">Active</span>
<span class="badge bg-danger">Offline</span>
```

**DOPO** (Ottimizzato):
```html
<span class="badge bg-gradient-success badge-pill shadow-sm">
    <span class="badge-dot bg-success me-1"></span>Active
</span>
<span class="badge bg-gradient-danger badge-pill">Offline</span>
```

**Varianti**:
- `badge-pill` - Rounded pill shape
- `badge-dot` - Dot indicator
- `badge-pulse` - Pulsing animation (per stati critical)
- `shadow-sm` - Subtle shadow

**Color mapping**:
- Online/Success → `bg-gradient-success`
- Offline/Secondary → `bg-gradient-secondary`  
- Warning/Pending → `bg-gradient-warning`
- Error/Danger → `bg-gradient-danger`
- Info → `bg-gradient-info`

---

### 6. Forms - Input Enhancement

**PRIMA**:
```html
<div class="form-group">
    <label>Name</label>
    <input type="text" class="form-control">
</div>
```

**DOPO** (Ottimizzato):
```html
<div class="form-group">
    <label class="form-label text-sm font-weight-bolder text-dark">Name</label>
    <input type="text" class="form-control form-control-soft border-radius-lg">
</div>

<!-- Input Group Version -->
<div class="input-group input-group-outline border-radius-lg">
    <span class="input-group-text"><i class="fas fa-search"></i></span>
    <input type="text" class="form-control" placeholder="Search...">
</div>
```

**Classi**:
- `form-control-soft` - Border soft + focus animation
- `input-group-outline` - Border unificato + focus animation
- `border-radius-lg` - Corners arrotondati

---

### 7. Modals - Consistent Styling

**Aggiungere al modal**:
```html
<div class="modal fade" id="exampleModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-radius-xl shadow-2xl-soft">
            <div class="modal-header border-bottom">
                <h5 class="modal-title font-weight-bolder">Title</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                Content
            </div>
            <div class="modal-footer border-top">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn bg-gradient-primary">Save</button>
            </div>
        </div>
    </div>
</div>
```

**Key points**:
- `modal-dialog-centered` - Vertical centering
- `border-radius-xl` - Rounded modal
- `shadow-2xl-soft` - Deep shadow
- `p-4` - Adequate padding
- `border-bottom` / `border-top` - Subtle dividers

---

### 8. Icon Shapes - Consistent Sizing

**Utilizzo**:
```html
<!-- Extra Small -->
<div class="icon icon-shape icon-xxs bg-gradient-primary rounded-circle">
    <i class="fas fa-check"></i>
</div>

<!-- Small -->
<div class="icon icon-shape icon-sm bg-gradient-success shadow-success rounded-circle">
    <i class="fas fa-user"></i>
</div>

<!-- Medium (default dashboard) -->
<div class="icon icon-shape icon-md bg-gradient-info shadow text-center border-radius-md">
    <i class="ni ni-world text-lg opacity-10"></i>
</div>

<!-- Large -->
<div class="icon icon-shape icon-lg bg-gradient-warning shadow-warning rounded-circle">
    <i class="fas fa-rocket text-white"></i>
</div>

<!-- Extra Large -->
<div class="icon icon-shape icon-xl bg-gradient-danger rounded-circle">
    <i class="fas fa-bell text-white text-xl"></i>
</div>
```

**Sizes**:
- `icon-xxs` - 24px
- `icon-xs` - 32px
- `icon-sm` - 40px
- `icon-md` - 48px
- `icon-lg` - 56px
- `icon-xl` - 64px

---

## 🎨 Gradient Colors Reference

### Primary Gradients
```css
.bg-gradient-primary          /* Purple to Blue */
.bg-gradient-success          /* Green to Blue */
.bg-gradient-info             /* Blue tones */
.bg-gradient-warning          /* Yellow to Orange */
.bg-gradient-danger           /* Red tones */
.bg-gradient-dark             /* Dark gray */
```

### Custom Gradients
```css
.bg-gradient-primary-to-secondary
.bg-gradient-success-to-info
.bg-gradient-warning-to-danger
.bg-gradient-dark-to-primary
```

### Soft Backgrounds (10% opacity)
```css
.bg-soft-primary
.bg-soft-success
.bg-soft-info
.bg-soft-warning
.bg-soft-danger
```

### Text Gradients
```html
<h1 class="text-gradient-primary">Gradient Text</h1>
<p class="text-gradient-success">Success Gradient</p>
<span class="text-gradient-warning">Warning Text</span>
```

---

## 📱 Responsive Best Practices

### Mobile Stacking
```html
<div class="row">
    <!-- Desktop: 3 columns, Mobile: 1 column -->
    <div class="col-xl-4 col-lg-6 col-12 mb-4">
        <div class="card">...</div>
    </div>
</div>
```

### Hide on Mobile
```html
<div class="d-none d-md-block">
    <!-- Hidden on mobile, visible tablet+ -->
</div>

<div class="d-md-none">
    <!-- Visible only on mobile -->
</div>
```

### Mobile Stack Utility
```html
<div class="d-flex mobile-stack">
    <!-- Auto stack on mobile -->
</div>
```

---

## ✨ Animation Classes

### Entrance Animations
```html
<div class="fade-in">Content fades in</div>
<div class="slide-up">Content slides up</div>
```

### Interactive Animations
```html
<div class="card-hover">Lifts on hover</div>
<div class="card-hover-lift">Lifts + scales on hover</div>
<button class="btn-hover-shadow">Shadow on hover</button>
```

### Loading States
```html
<div class="skeleton" style="height: 20px; width: 100px;"></div>
```

### Pulsing Badge
```html
<span class="badge badge-pulse bg-gradient-danger">Critical</span>
```

---

## 🔧 File-by-File Optimization Checklist

### Dashboard (`resources/views/acs/dashboard.blade.php`)
- [ ] Stat cards: Add `shadow-lg-soft`, `border-radius-xl`, `card-hover`
- [ ] Charts: Wrap in `border-radius-lg` card
- [ ] Tables: Add `table-hover`, `table-soft`
- [ ] Buttons: Replace `btn-primary` with `bg-gradient-primary`
- [ ] Icons: Standardize sizes with `icon-sm/md/lg`

### Devices (`resources/views/acs/devices.blade.php`)
- [ ] Device cards: Add `card-hover`, `shadow-lg-soft`
- [ ] Filter toolbar: Use `input-group-outline`
- [ ] Protocol badges: Change to `bg-gradient-*`
- [ ] Action buttons: Add `btn-hover-shadow`
- [ ] Grid layout: Verify responsive cols

### Alarms (`resources/views/acs/alarms/index.blade.php`)
- [ ] Alarm cards: Add severity-based `shadow-*` colors
- [ ] Status badges: Use `badge-pill`, `badge-dot`
- [ ] Action buttons: Gradient backgrounds
- [ ] Table: Add `table-hover`

### Device Detail (`resources/views/acs/device-detail.blade.php`)
- [ ] Info cards: Add `shadow-lg-soft`
- [ ] Parameter tables: Add `table-soft`
- [ ] Action buttons: Gradient + hover effects
- [ ] Tabs: Enhance spacing

### Diagnostics (`resources/views/acs/diagnostics.blade.php`)
- [ ] Test result cards: Add `fade-in` animation
- [ ] Status badges: Use gradient badges
- [ ] Charts: Border radius on wrapper
- [ ] Forms: Use `form-control-soft`

### Provisioning (`resources/views/acs/provisioning.blade.php`)
- [ ] Task cards: Add hover effects
- [ ] Status indicators: Badge gradients
- [ ] Filters: Input group outline
- [ ] Timeline: Soft shadows

### Customers/Users (`resources/views/acs/customers.blade.php`, `users.blade.php`)
- [ ] List cards: Hover lift effect
- [ ] Profile images: Border radius circle
- [ ] Role badges: Gradient badges
- [ ] Tables: Soft borders + hover

### AI Assistant (`resources/views/acs/ai-assistant.blade.php`)
- [ ] Chat cards: Shadow soft
- [ ] Message bubbles: Border radius lg
- [ ] Action buttons: Gradient primary
- [ ] Loading states: Skeleton animation

### Modals (all `/modals/*.blade.php`)
- [ ] Wrapper: `border-radius-xl`, `shadow-2xl-soft`
- [ ] Header: `border-bottom` class
- [ ] Footer: `border-top` class
- [ ] Buttons: Gradient backgrounds

---

## 📦 Quick Search & Replace Patterns

### Global Button Update
**Find**: `class="btn btn-primary"`  
**Replace**: `class="btn bg-gradient-primary btn-hover-shadow"`

### Card Shadow Enhancement
**Find**: `class="card"`  
**Replace**: `class="card shadow-lg-soft border-radius-xl"`

### Badge Gradient
**Find**: `class="badge bg-success"`  
**Replace**: `class="badge bg-gradient-success badge-pill"`

### Table Enhancement
**Find**: `class="table"`  
**Replace**: `class="table table-hover table-soft"`

---

## 🎯 Priority Order

1. **High Impact** (Do first):
   - Dashboard
   - Devices page
   - Device detail
   - Alarms

2. **Medium Impact**:
   - Diagnostics
   - Provisioning
   - Customers/Users
   - AI Assistant

3. **Low Impact** (Optional):
   - Profile pages
   - Settings
   - Secondary modals
   - Footer components

---

## ✅ Before/After Examples

### Example 1: Stat Card

**PRIMA**:
```html
<div class="card">
    <div class="card-body p-3">
        <h6>Total Devices</h6>
        <h2>1,234</h2>
    </div>
</div>
```

**DOPO**:
```html
<div class="card shadow-lg-soft border-radius-xl card-hover fade-in">
    <div class="card-body p-3">
        <div class="d-flex align-items-center">
            <div class="icon icon-shape icon-md bg-gradient-primary shadow-primary rounded-circle me-3">
                <i class="fas fa-server opacity-80"></i>
            </div>
            <div>
                <p class="text-sm mb-0 text-uppercase font-weight-bold opacity-70">Total Devices</p>
                <h5 class="font-weight-bolder mb-0 mt-1">1,234</h5>
                <p class="text-xs text-success mb-0">
                    <i class="fas fa-arrow-up"></i> +12% this month
                </p>
            </div>
        </div>
    </div>
</div>
```

---

## 🚀 Next Steps

1. **Apply patterns** seguendo i template sopra
2. **Test responsive** su mobile/tablet
3. **Verify animations** funzionano correttamente
4. **Document custom** modifiche nel proprio team

---

## 📚 Resources

- **Soft UI Dashboard Docs**: https://www.creative-tim.com/learning-lab/bootstrap/cards/soft-ui-dashboard
- **Bootstrap 5 Docs**: https://getbootstrap.com/docs/5.0/getting-started/introduction/
- **Custom CSS**: `/public/assets/css/soft-ui-enhancements.css`

---

**Status**: 🎨 Ready to use! Tutte le utility sono caricate globalmente.
