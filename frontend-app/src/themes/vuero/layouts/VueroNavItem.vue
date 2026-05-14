<template>
  <!-- Single link -->
  <li v-if="item.type === 'link'">
    <router-link :to="item.to" class="single-link" active-class="is-active">
      <span class="icon">
        <i :class="item.icon" />
      </span>
      <span class="label-text">{{ item.label }}</span>
      <span v-if="item.badge" class="badge">{{ item.badge }}</span>
    </router-link>
  </li>

  <!-- Divider -->
  <li v-else-if="item.type === 'divider'" class="divider" />

  <!-- Collapse group -->
  <li v-else-if="item.type === 'collapse'" class="has-children" :class="{ active: isOpen }">
    <div class="collapse-wrap">
      <a
        role="button"
        tabindex="0"
        class="single-link"
        @click="isOpen = !isOpen"
        @keydown.enter.prevent="isOpen = !isOpen"
      >
        <span class="icon">
          <i :class="item.icon" />
        </span>
        <span class="label-text">{{ item.label }}</span>
        <i class="pi pi-chevron-right chevron" :class="{ 'is-rotated': isOpen }" />
      </a>
    </div>
    <ul v-show="isOpen" class="sub-menu">
      <li v-for="child in item.children" :key="child.to">
        <router-link :to="child.to" class="is-submenu" active-class="is-active">
          <i v-if="child.icon" :class="child.icon" style="margin-right:0.5rem;font-size:0.85rem" />
          <span>{{ child.label }}</span>
        </router-link>
      </li>
    </ul>
  </li>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'

const props = defineProps({
  item: { type: Object, required: true },
})

const route = useRoute()

// Auto-expand if any child route is active
const isOpen = ref(
  props.item.type === 'collapse' &&
  props.item.children?.some(c => route.path.startsWith(c.to))
)
</script>

<style scoped>
li {
  display: flex;
  justify-content: flex-start;
  align-items: center;
  list-style: none;
}

li.divider {
  cursor: default;
  pointer-events: none;
  height: 1px;
  margin: 8px 20px;
  background: rgba(0, 0, 0, 0.08);
  display: block;
}

li.has-children {
  display: block;
}

/* Single link */
.single-link {
  font-family: var(--font-alt, 'Montserrat Variable', sans-serif);
  display: flex;
  align-items: center;
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--light-text, #a2a5b9);
  padding: 0.75rem 0.85rem;
  margin: 0.15rem 1.25rem;
  border-radius: var(--radius-large, 6px);
  text-decoration: none;
  transition: background 0.2s, color 0.2s;
  cursor: pointer;
  border: none;
  background: none;
  width: calc(100% - 2.5rem);
}
.single-link:hover,
.single-link:focus {
  background: var(--widget-grey, #f5f6fa);
  color: var(--dark-text, #283252);
}
.single-link.is-active {
  background: var(--widget-grey, #f5f6fa);
  color: var(--primary, #41b3a3);
  font-weight: 600;
}
.single-link.is-active .icon i {
  color: var(--primary, #41b3a3);
}

.icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.5rem;
  height: 1.5rem;
  margin-right: 0.85rem;
  flex-shrink: 0;
  font-size: 1rem;
}
.icon i {
  font-size: 1rem;
  color: var(--light-text, #a2a5b9);
  transition: color 0.2s;
}
.single-link:hover .icon i {
  color: var(--primary, #41b3a3);
}

.label-text {
  flex: 1;
}

.badge {
  margin-left: auto;
  background: var(--primary, #41b3a3);
  color: #fff;
  font-size: 0.7rem;
  font-weight: 600;
  line-height: 1;
  padding: 0.25rem 0.5rem;
  border-radius: 9999px;
}

/* Collapse chevron */
.chevron {
  margin-left: auto;
  font-size: 0.7rem;
  color: var(--light-text, #a2a5b9);
  transition: transform 0.25s;
}
.chevron.is-rotated {
  transform: rotate(90deg);
}

/* Sub-menu */
.sub-menu {
  list-style: none;
  padding: 0;
  margin: 0;
}
.sub-menu li {
  display: block;
}
.is-submenu {
  display: flex;
  align-items: center;
  font-size: 0.85rem;
  font-weight: 400;
  color: var(--light-text, #a2a5b9);
  padding: 0.6rem 0.85rem 0.6rem 3rem;
  margin: 0.1rem 1.25rem;
  border-radius: var(--radius-large, 6px);
  text-decoration: none;
  transition: background 0.2s, color 0.2s;
}
.is-submenu:hover,
.is-submenu.is-active {
  background: var(--widget-grey, #f5f6fa);
  color: var(--primary, #41b3a3);
  font-weight: 500;
}

/* Colored theme overrides */
:deep(.is-colored) .single-link { color: rgba(255,255,255,0.65); }
:deep(.is-colored) .single-link:hover,
:deep(.is-colored) .single-link.is-active {
  background: rgba(255,255,255,0.08);
  color: #fff;
}
:deep(.is-colored) .icon i { color: rgba(255,255,255,0.5); }
:deep(.is-colored) .single-link.is-active .icon i,
:deep(.is-colored) .single-link:hover .icon i { color: var(--primary, #41b3a3); }
</style>
