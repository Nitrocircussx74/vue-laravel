import Axios from "axios";
import { attempt } from "lodash";

const state ={
    user:{},
    token:null,
    is_login:false,
  
};
const mutations ={
    SET_TOKEN(state,token){
        state.token = token;
    },
    SET_USER(state,user){
        state.user=user;
    },
    SET_LOGIN(state,is_login){
        state.is_login=is_login;
    }

};
const getters ={
  authenticated(state){
    return state.token && state.user
  },
  token(state){
    return state.token
  },
  user(state){
    return state.user
  },
  is_login(state){
    return state.is_login
  }

};
const actions ={
    async loginUser({dispatch},user)
    {
        let res = await axios.post("api/login",user)
      return  dispatch('attempt',res.data.access_token);
    },
    async attempt({commit},token){
        commit('SET_TOKEN',token)
        try{
            let res = await axios.get('api/getuser')
            localStorage.setItem('token',token)
            commit('SET_USER',res.data)
            commit('SET_LOGIN',true)
          

        }catch(e){
            commit('SET_TOKEN',null)
            commit('SET_USER',null)
            commit('SET_LOGIN',false)
            console.log('failed');
        }

    },
    logoutUser({commit})
    {     
        return axios.post("api/logout").then(()=>{
            commit('SET_TOKEN',null)
            commit('SET_USER',null)
            commit('SET_LOGIN',false)
            localStorage.removeItem("token");
        });
    },

};
export default { 
    namespaced:true,
    state,
    getters,
    actions,
    mutations
}
