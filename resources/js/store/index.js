import Vue from 'vue'
import axios from 'axios'
import Vuex from 'vuex'
Vue.use(Vuex)
import createPersistedState from 'vuex-persistedstate'
import currentUser from './modules/currentUser'
import userList from './modules/userList'
import PropertyList from './modules/PropertyList'
export default new Vuex.Store({
  modules:{
    currentUser,
    userList,
    PropertyList
  },
  plugins: [createPersistedState()]
   

  // state: {
  //     count:100
  // },
  // mutations: {
  //   setCount(state,value){
  //     state.count = value
  //   }
  // },
  // getters: {
  //   getCount(state){
  //     return state.count
  //   }
  // },
  // actions: {
  //   addAction(context){
  //     context.commit('setCount', this.getters.getCount+1)
  //   },
  //   delAction(context){
  //     context.commit('setCount', this.getters.getCount-1)
  //   }
  // },
 

})