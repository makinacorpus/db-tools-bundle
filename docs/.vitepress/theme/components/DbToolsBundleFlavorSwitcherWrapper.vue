<script setup lang="ts">
import DbToolsBundleFlavorSwitcher from './DbToolsBundleFlavorSwitcher.vue';
import { withBase } from 'vitepress'
</script>

<template>
  <details open>
    <summary>
      Preferred flavor
    </summary>
    <div>
      <DbToolsBundleFlavorSwitcher />
      <a :href="withBase('/getting-started/flavors')" class="info">?</a>
    </div>
  </details>
</template>

<style scoped>
details {
  font-size: 12px;
  border-bottom: 1px solid var(--vt-c-divider-light);
  transition: border-color .5s,background-color .5s ease;
  position: sticky;
  top: -33px;
  background-color: var(--vp-c-bg-alt);
  padding-top: 10px;
  padding-bottom: 1px;
  z-index: 10;
}
details > div {
  position: relative;
  border-radius: 8px;
  background-color: var(--vp-c-bg);
  padding: 12px;
  margin: 6px 0 12px;
}

a.info {
  position: absolute;
  top: 10px;
  right: 10px;
  display: block;
  text-align: center;
  color: var(--vp-c-brand);
  width: 17px;
  height: 17px;
  border: solid 1px var(--vp-c-brand);
  border-radius: 100%;
  line-height: 14px;
}

@media (min-width: 960px) {
  details {
    top: -.5px;
  }
}
</style>
