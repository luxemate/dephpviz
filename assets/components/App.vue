<template>
  <div class="dephpviz-app">
    <header class="app-header">
      <h1>DePhpViz</h1>
      <search-panel @search="handleSearch" />
    </header>
    <graph-visualization 
      :graphData="graphData" 
      :searchTerm="searchTerm" 
      class="graph-container" 
    />
  </div>
</template>

<script>
import axios from 'axios';
import GraphVisualization from './GraphVisualization.vue';
import SearchPanel from './SearchPanel.vue';

export default {
  components: {
    GraphVisualization,
    SearchPanel
  },
  data() {
    return {
      graphData: null,
      searchTerm: '',
      loading: true,
      error: null
    };
  },
  methods: {
    handleSearch(term) {
      this.searchTerm = term;
    },
    async fetchGraphData() {
      try {
        this.loading = true;
        const response = await axios.get('/api/graph');
        this.graphData = response.data;
        this.loading = false;
      } catch (error) {
        this.error = 'Failed to load graph data';
        this.loading = false;
        console.error(error);
      }
    }
  },
  mounted() {
    this.fetchGraphData();
  }
};
</script>

<style lang="scss" scoped>
.dephpviz-app {
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow: hidden;
}

.app-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background-color: #f8f9fa;
  border-bottom: 1px solid #dee2e6;
}

.graph-container {
  flex: 1;
  overflow: hidden;
}
</style>
