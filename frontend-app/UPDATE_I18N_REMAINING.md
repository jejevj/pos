# Remaining i18n Implementation

## Status Update

### ✅ Already Completed (5 files)
1. DashboardLayout.vue - Language switcher + menu translations
2. LoginView.vue - Full i18n
3. RegisterView.vue - Full i18n  
4. UsersView.vue - Full CRUD i18n
5. RolesView.vue - Full CRUD i18n

### 🔄 To Be Completed (9 files)

#### High Priority (CRUD Pages)
1. **PermissionsView.vue** - Permission management CRUD
2. **MenusView.vue** - Menu management with drag & drop
3. **SettingsView.vue** - Settings with tabs

#### Medium Priority
4. **DashboardView.vue** - Main dashboard page
5. **HomeView.vue** - Home page (if used)
6. **ReportsView.vue** - Reports page (if used)

#### Low Priority (Error Pages - 5 files)
7. ForbiddenView.vue
8. NotFoundView.vue  
9. ServerErrorView.vue
10. ServiceUnavailableView.vue
11. UnauthorizedView.vue

## Implementation Strategy

For each file, we need to:
1. Import `useI18n` from 'vue-i18n'
2. Add `const { t } = useI18n()` in script setup
3. Replace hardcoded text with `$t()` in templates
4. Replace hardcoded text with `t()` in script
5. Make breadcrumbItems computed if it uses translations

## Quick Reference

### Template Usage
```vue
<span>{{ $t('common.dashboard') }}</span>
<Button :label="$t('common.save')" />
```

### Script Usage  
```javascript
const { t } = useI18n()
toast.add({
  summary: t('messages.success'),
  detail: t('messages.savedSuccessfully')
})
```

### Computed Breadcrumbs
```javascript
const breadcrumbItems = computed(() => [
  { label: t('common.admin'), to: '/dashboard' },
  { label: t('menu.permissionManagement') }
])
```

## Next Action

I will now proceed to update all remaining files starting with the high-priority CRUD pages.
