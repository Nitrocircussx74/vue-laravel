

require('./bootstrap');

window.Vue = require('vue');
import Vue from 'vue'
import Vuex from 'vuex'
import vuetify from '../plugins/vuetify'
// import 'material-design-icons-iconfont/dist/material-design-icons.css' 
import 'material-design-icons-iconfont/dist/material-design-icons.css'
import '@mdi/font/css/materialdesignicons.css' 
import router from './router'
import store from "./store/index";


Vue.use(Vuex)

/**
 * The following block of code may be used to automatically register your
 * Vue components. It will recursively scan this directory for the Vue
 * components and automatically register them with their "basename".
 *
 * Eg. ./components/ExampleComponent.vue -> <example-component></example-component>
 */


Vue.component('example-component', require('./components/ExampleComponent.vue'));
Vue.component('app', require('./app.vue').default);

const app = new Vue({
    router,
    store,
    vuetify,
    icons: {
        iconfont: 'md',
      },
    // icons: {
    //     iconfont: 'mdi', // default - only for display purposes
    //   },
    el: '#app',
})
