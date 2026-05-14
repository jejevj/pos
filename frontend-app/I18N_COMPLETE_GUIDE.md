# Complete i18n Implementation Guide

## ✅ What's Already Done

### Core Setup (100% Complete)
- ✅ i18n configuration (`src/i18n/index.js`)
- ✅ English translations (`src/i18n/locales/en.json`)
- ✅ Indonesian translations (`src/i18n/locales/id.json`)
- ✅ Language composable (`src/composables/useLanguage.js`)
- ✅ Plugin registration in `main.js`

### Completed Views (5/14 files = 36%)
1. ✅ **DashboardLayout.vue** - Language switcher working, menu items from backend
2. ✅ **LoginView.vue** - All text translated including session expired dialog
3. ✅ **RegisterView.vue** - All form labels and messages translated
4. ✅ **UsersView.vue** - Complete CRUD with breadcrumbs, dialogs, toasts
5. ✅ **RolesView.vue** - Complete CRUD with permissions dialog

## 🔄 Remaining Work

### High Priority - Admin CRUD (2 files)
6. ⏳ **PermissionsView.vue** - Permission management
7. ⏳ **MenusView.vue** - Menu management with drag & drop

### Medium Priority - Main Pages (3 files)
8. ⏳ **SettingsView.vue** - Settings with 4 tabs
9. ⏳ **DashboardView.vue** - Main dashboard
10. ⏳ **ReportsView.vue** - Reports page

### Low Priority - Error Pages (5 files)
11. ⏳ **ForbiddenView.vue** - 403 error
12. ⏳ **NotFoundView.vue** - 404 error
13. ⏳ **ServerErrorView.vue** - 500 error
14. ⏳ **ServiceUnavailableView.vue** - 503 error
15. ⏳ **UnauthorizedView.vue** - 401 error

## 📋 Implementation Checklist

For each remaining file, apply these changes:

### 1. Script Section
```javascript
// Add import
import { useI18n } from 'vue-i18n'

// In setup
const { t } = useI18n()

// Make breadcrumbs computed
const breadcrumbItems = computed(() => [
  { label: t('common.admin'), to: '/dashboard' },
  { label: t('menu.permissionManagement') }
])

// Update toast messages
toast.add({
  severity: 'success',
  summary: t('messages.success'),
  detail: t('messages.savedSuccessfully'),
  life: 3000
})
```

### 2. Template Section
```vue
<!-- Page titles -->
<span>{{ $t('menu.permissionManagement') }}</span>

<!-- Buttons -->
<Button :label="$t('common.save')" />
<Button :label="$t('common.cancel')" />

<!-- Form labels -->
<label>{{ $t('permission.permissionName') }}</label>

<!-- Placeholders -->
<InputText :placeholder="$t('common.search')" />

<!-- Dialog headers -->
:header="dialogMode === 'create' ? $t('permission.addPermission') : $t('permission.editPermission')"

<!-- Confirmation messages -->
<p>{{ $t('messages.confirmDelete', { item: itemToDelete?.name }) }}</p>
```

## 🎯 Translation Keys Reference

All keys are already defined in `en.json` and `id.json`:

### Common Keys
- `common.home`, `common.dashboard`, `common.settings`
- `common.save`, `common.cancel`, `common.delete`, `common.edit`
- `common.create`, `common.update`, `common.search`
- `common.loading`, `common.noData`

### Menu Keys
- `menu.userManagement`
- `menu.roleManagement`
- `menu.permissionManagement`
- `menu.menuManagement`

### Permission Keys
- `permission.permissions`, `permission.addPermission`
- `permission.editPermission`, `permission.deletePermission`
- `permission.permissionKey`, `permission.permissionName`
- `permission.permissionDescription`, `permission.permissionGroup`
- `permission.usedBy`

### Menu Management Keys
- `menuMgmt.menus`, `menuMgmt.addMenu`
- `menuMgmt.editMenu`, `menuMgmt.deleteMenu`
- `menuMgmt.menuTitle`, `menuMgmt.menuName`
- `menuMgmt.menuIcon`, `menuMgmt.menuRoute`
- `menuMgmt.menuUrl`, `menuMgmt.menuParent`
- `menuMgmt.menuOrder`, `menuMgmt.menuActive`

### Settings Keys
- `settings.general`, `settings.profile`
- `settings.security`, `settings.notifications`
- `settings.language`, `settings.timezone`
- `settings.fullName`, `settings.phoneNumber`
- `settings.currentPassword`, `settings.newPassword`

### Message Keys
- `messages.success`, `messages.error`
- `messages.confirmDelete` (with {item} parameter)
- `messages.cannotUndo`
- `messages.savedSuccessfully`
- `messages.deletedSuccessfully`
- `messages.updatedSuccessfully`
- `messages.createdSuccessfully`

## 🚀 How to Test

1. Start the frontend: `cd frontend-app && npm run dev`
2. Login to the application
3. Click the language switcher (🇬🇧/🇮🇩) in the header
4. Navigate through all pages and verify:
   - All text changes language
   - Breadcrumbs translate correctly
   - Toast messages appear in correct language
   - Dialog titles and buttons translate
   - Form labels and placeholders translate

## 📝 Notes

- Language preference is saved to localStorage
- Menu items come from backend (not translated by i18n)
- Sidebar menu titles are from database
- All static UI text should use i18n
- Error messages from API may not be translated (backend responsibility)

## ✨ Current Status

**Progress: 36% Complete (5/14 files)**

The foundation is solid. All translation keys are ready. Now we just need to apply the pattern to the remaining 9 files.

---

**Next Steps:**
1. Update PermissionsView.vue
2. Update MenusView.vue  
3. Update SettingsView.vue
4. Update DashboardView.vue
5. Update error pages (batch update - they're simple)
6. Final testing across all pages
