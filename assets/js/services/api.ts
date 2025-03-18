import axios from 'axios';
import { GraphData } from '../types';

// Default API URL if not set in the window config
const DEFAULT_API_URL = '/api/graph';
const DEFAULT_STATUS_URL = '/api/status';

/**
 * Get the API URL from the window config
 */
export const getApiUrl = (): string => {
  return window.DePhpVizConfig?.apiUrl || DEFAULT_API_URL;
};

/**
 * Get the status URL from the window config
 */
export const getStatusUrl = (): string => {
  return window.DePhpVizConfig?.statusUrl || DEFAULT_STATUS_URL;
};

/**
 * Fetch the graph data from the API
 */
export const fetchGraphData = async (): Promise<GraphData> => {
  try {
    const response = await axios.get(getApiUrl());
    return response.data as GraphData;
  } catch (error) {
    console.error('Failed to fetch graph data:', error);
    throw error;
  }
};

/**
 * Check if the API is available
 */
export const checkApiStatus = async (): Promise<boolean> => {
  try {
    const response = await axios.get(getStatusUrl());
    return response.status === 200;
  } catch (error) {
    console.error('API status check failed:', error);
    return false;
  }
};
