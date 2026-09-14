import axios from 'axios';

const api = axios.create({
    headers: {
        Accept: 'application/json',
    },
    withCredentials: true,
    withXSRFToken: true,
});

export default api;
