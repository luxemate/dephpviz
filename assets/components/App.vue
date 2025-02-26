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
        console.log('Fetching graph data from:', window.DePhpVizConfig?.apiUrl || '/api/graph');

        const response = await axios.get(window.DePhpVizConfig?.apiUrl || '/api/graph');

        // Debug the response
        console.log('API Response:', response);

        // Store the raw data object
        this.graphData = response.data;

        // Log data properties
        console.log('Nodes count:', Object.keys(this.graphData.nodes || {}).length);
        console.log('Edges count:', Object.keys(this.graphData.edges || {}).length);

        this.loading = false;
      } catch (error) {
        this.error = `Failed to load graph data: ${error.message}`;
        this.loading = false;
        console.error('Graph data fetch error:', error);
      }
    }
  },
  mounted() {
    this.fetchGraphData();
  }
};
</script>
