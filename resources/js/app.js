

require('./bootstrap');

window.Vue = require('vue');

import Vue from 'vue'
import Axios from 'axios';
import vuetify from '../plugins/vuetify'
import 'material-design-icons-iconfont/dist/material-design-icons.css'
import '@mdi/font/css/materialdesignicons.css' 
import routes from './router'
import store from "./store";
import VueRouter from 'vue-router';
import App from './App.vue'

Vue.use(VueRouter);

Vue.component('spinner',require('vue-simple-spinner'));

const router = new VueRouter({
    mode:'history',
    routes
 });

Vue.prototype.$http = Axios;
Vue.prototype.$http.interceptors.response.use(undefined, function (error) {
    const statusCode = error.response ? error.response.status : null;
    if (statusCode === 404) {
        alert('The requested resource does not exist or has been deleted')
    }
    if (statusCode === 401) {
        alert('Please login to access this resource')
        store.dispatch('auth/logout');
    }
    return Promise.reject(error);
})

Vue.prototype.$http.interceptors.request.use(function(config) {
    const token = localStorage.getItem('token');
    if ( token != null ) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
}, function(err) {
    return Promise.reject(err);
});

new Vue({
    router,
    store,
    vuetify,
    icons: {
        iconfont: 'md',
    },
    // el: '#app', 
    render:h=>h(App)
})
.$mount('#app');
