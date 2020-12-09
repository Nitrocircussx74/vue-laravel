import Axios from "axios";

const state ={
    user:{},
};
const getters ={
    user(state){
        return state.user
      },
};
const actions ={
        async getUser({commit})
        {
        let res = await axios.get("api/admin/all")
        commit('SET_USER',res.data);
        return res;
        },
        async saveAdmin({dispatch},data)
        {
            let res = await axios.post("api/admin/save",data)
            // return  dispatch('getUser');
        },
        async delAdmin({dispatch},data)
        {
            let res = await axios.post("api/admin/del",data)
            // return  dispatch('getUser');
        },
};
const mutations ={
    SET_USER(state,data){
        state.user=data;
    }

};

export default { 
    namespaced:true,
    state,
    getters,
    actions,
    mutations
}