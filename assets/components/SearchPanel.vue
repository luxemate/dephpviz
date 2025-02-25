<template>
  <div class="search-panel">
    <div class="search-input-container">
      <input
        type="text"
        v-model="searchInput"
        placeholder="Search classes..."
        class="search-input"
        @input="emitSearch"
      />
      <button v-if="searchInput" @click="clearSearch" class="clear-button">
        ×
      </button>
    </div>
  </div>
</template>

<script>
import { debounce } from 'lodash';

export default {
  data() {
    return {
      searchInput: ''
    };
  },
  methods: {
    emitSearch: debounce(function() {
      this.$emit('search', this.searchInput);
    }, 300),
    clearSearch() {
      this.searchInput = '';
      this.$emit('search', '');
    }
  }
};
</script>

<style lang="scss" scoped>
.search-panel {
  display: flex;
  align-items: center;
}

.search-input-container {
  position: relative;
  width: 300px;
}

.search-input {
  width: 100%;
  padding: 0.5rem 2rem 0.5rem 0.75rem;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 1rem;
  line-height: 1.5;
  
  &:focus {
    outline: none;
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
  }
}

.clear-button {
  position: absolute;
  right: 0.5rem;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  font-size: 1.2rem;
  line-height: 1;
  color: #6c757d;
  padding: 0 0.25rem;
  cursor: pointer;
  
  &:hover {
    color: #212529;
  }
}
</style>
