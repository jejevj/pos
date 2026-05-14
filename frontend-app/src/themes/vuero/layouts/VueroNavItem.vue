<template>
  <!-- Divider -->
  <li v-if="item.type === 'divider'" class="nav-divider" />

  <!-- Single link -->
  <li v-else-if="item.type === 'link' || !item.type" class="nav-item">
    <router-link :to="item.to || '#'" class="single-link" active-class="is-active">
      <span class="icon">
        <i :class="item.icon || 'pi pi-circle'"></i>
      </span>
      <span class="link-label">{{ item.label }}</span>
      <span v-if="item.badge !== undefined" class="badge">{{ item.badge }}</span>
    </router-link>
  </li>

  <!-- Collapse group -->
  <li v-else-if="item.type === 'collapse'" class="nav-item has-children" :class="{ active: isOpen }">
    <div class="collapse-wrap">
      <a class="collapse-trigger" @click="isOpen = !isOpen">
        <span class="icon">
          <i :class="item.icon || 'pi pi-folder'"></i>
        </span>
        <span>{{ item.label }}</span>
        <i class="pi pi-chevron-right collapse-arrow" :class="{ rotated: isOpen }"></i>
      </a>
    </div>
    <Transition name="collapse">
      <ul v-if="isOpen" class="sub-menu">
        <li v-for="child in item.children" :key="child.label">
          <router-link :to="child.to || '#'" class="is-submenu" active-class="is-active">
            <i class="pi pi-minus sub-dot"></i>
            {{ child.label }}
          </router-link>
        </li>
      </ul>
    </Transition>
  </li>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'

const props = defineProps({
  item: { type: Object, required: true }
})

const route = useRoute()
const isOpen = ref(
  props.item.children?.some(c => route.path.startsWith(c.to)) ?? false
)
</script>

<style scoped>
.nav-divider {
  height: 1px;
  background: var(--border, #e5e7eb);
  margin: 0.5rem 1.5rem;
}

.nav-item { list-style: none; }

.single-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.7rem 1.5rem;
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--light-text, #6b7280);
  text-decoration: none;
  border-radius: 0.65rem;
  margin: 0.1rem 1rem;
  transition: background 0.2s, color 0.2s;
}
.single-link:hover, .single-link.is-active {
  background: var(--widget-grey, #f3f4f6);
  color: var(--dark-text, #1f2937);
}
.single-link.is-active .icon { color: var(--primary, #3b82f6); }

.icon { font-size: 1.1rem; flex-shrink: 0; }
.link-label { flex: 1; }
.badge {
  margin-left: auto;
  background: var(--primary, #3b82f6);
  color: white;
  border-radius: 100px;
  padding: 0.15rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
}

/* Collapse */
.has-children { display: block; }
.collapse-trigger {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.7rem 1.5rem;
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--light-text, #6b7280);
  cursor: pointer;
  border-radius: 0.65rem;
  margin: 0.1rem 1rem;
  transition: background 0.2s, color 0.2s;
}
.collapse-trigger:hover { background: var(--widget-grey, #f3f4f6); color: var(--dark-text, #1f2937); }
.collapse-arrow { margin-left: auto; font-size: 0.75rem; transition: transform 0.2s; }
.collapse-arrow.rotated { transform: rotate(90deg); }

.sub-menu { list-style: none; padding: 0; margin: 0; }
.is-submenu {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1.5rem 0.5rem 3.5rem;
  font-size: 0.875rem;
  color: var(--light-text, #6b7280);
  text-decoration: none;
  transition: color 0.2s;
}
.is-submenu:hover, .is-submenu.is-active { color: var(--primary, #3b82f6); font-weight: 500; }
.sub-dot { font-size: 0.5rem; }

.collapse-enter-active, .collapse-leave-active { transition: all 0.2s ease; overflow: hidden; }
.collapse-enter-from, .collapse-leave-to { max-height: 0; opacity: 0; }
.collapse-enter-to, .collapse-leave-from { max-height: 500px; opacity: 1; }
</style>
