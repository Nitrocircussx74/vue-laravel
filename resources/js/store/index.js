import Vue from 'vue'
import Vuex from 'vuex'
Vue.use(Vuex)

import currentUser from './modules/currentUser'
export default new Vuex.Store({
  modules:{
    currentUser
  },
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