

require('./bootstrap');
require('./store/subscriber');

window.Vue = require('vue');
import Vue from 'vue'

import vuetify from '../plugins/vuetify'

import 'material-design-icons-iconfont/dist/material-design-icons.css'
import '@mdi/font/css/materialdesignicons.css' 
import routes from './router'
import store from "./store";
import VueRouter from 'vue-router';
// import Main from './Main.vue'
import App from './App.vue'

Vue.use(VueRouter);
Vue.component('spinner',require('vue-simple-spinner'));


const router = new VueRouter({
    mode:'history',
    // mode: "hash",
    routes
 });



// Vue.component('login-form', require('./components/LoginForm.vue').default);
// Vue.component('app', require('./App.vue').default);


store.dispatch('currentUser/attempt',localStorage.getItem('token')).then(()=>{
    new Vue({
        router,
        store,
        vuetify,
        // components:{Main},
        icons: {
            iconfont: 'md',
          },
        // el: '#app', 
        render:h=>h(App)
    })
    .$mount('#app');
})


// const app = new Vue({
//     router,
//     store,
//     vuetify,
//     icons: {
//         iconfont: 'md',
//       },
//     // icons: {
//     //     iconfont: 'mdi', // default - only for display purposes
//     //   },
//     render:h=>h(Main)
//     // el: '#app',
// }).$mount('#app');
// // });

