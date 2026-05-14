#!/bin/bash

# Script to apply i18n to remaining Vue files
# This script documents the changes needed for each file

echo "=== i18n Implementation Script ==="
echo ""
echo "Files to update:"
echo "1. PermissionsView.vue"
echo "2. MenusView.vue"
echo "3. SettingsView.vue"
echo "4. DashboardView.vue"
echo "5. Error pages (5 files)"
echo ""
echo "Changes needed for each file:"
echo "- Import: import { useI18n } from 'vue-i18n'"
echo "- Setup: const { t } = useI18n()"
echo "- Template: Replace text with \$t('key')"
echo "- Script: Replace text with t('key')"
echo "- Breadcrumbs: Make computed if using translations"
echo ""
echo "Run this manually or use Kiro to apply changes"
