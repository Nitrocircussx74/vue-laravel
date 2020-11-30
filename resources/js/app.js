

require('./bootstrap');

window.Vue = require('vue');
import Vue from 'vue'


import vuetify from '../plugins/vuetify'
// import 'material-design-icons-iconfont/dist/material-design-icons.css' 
import 'material-design-icons-iconfont/dist/material-design-icons.css'
import '@mdi/font/css/materialdesignicons.css' 
import routes from './router'
import store from "./store";
import VueRouter from 'vue-router';
// import App from "./App.vue";

Vue.use(VueRouter);
Vue.component('spinner',require('vue-simple-spinner'));

const router = new VueRouter({
    routes
 });

// Vue.component('example-component', require('./components/ExampleComponent.vue'));

Vue.component('login-form', require('./components/LoginForm.vue').default);
Vue.component('app', require('./app.vue').default);
Vue.component(
    'passport-clients',
    require('./components/passport/Clients.vue').default
);

Vue.component(
    'passport-authorized-clients',
    require('./components/passport/AuthorizedClients.vue').default
);

Vue.component(
    'passport-personal-access-tokens',
    require('./components/passport/PersonalAccessTokens.vue').default
);


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
    // render:h=>h(App)
    el: '#app',
// }).$mount('#app');
});
