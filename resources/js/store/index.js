import Vue from 'vue';
import Vuex from 'vuex';
import { auth } from './auth.module';
import userList from './modules/userList'
import PropertyList from './modules/PropertyList'
Vue.use(Vuex);
export default new Vuex.Store({
  modules:{
    auth,
    userList,
    PropertyList,
  }
});
