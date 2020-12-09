import Axios from "axios";

const state ={
    property:{},
};
const getters ={

};
const actions ={
    getProperty(commit){
        axios.get("api/property/all").then(res=>{
            console.log(res.data);
            commit('setProperty',res.data);
        })
    },
};
const mutations ={
    setProperty(state,data){
        state.property=data;
    }

};

export default { 
    namespaced:true,
    state,
    getters,
    actions,
    mutations
}