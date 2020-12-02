import Axios from "axios";

const state ={
    user:{},
};
const getters ={

};
const actions ={
    // getUser(commit){
    //     axios.get("api/current").then(res=>{
    //         commit('setUser',res.data);
    //     })
    // },
    loginUser({state,commit},user)
    {
            axios.post("api/user/login",{
                email:user.email,
                password:user.password
            }).then(res=>{
                if(res.token)
            {
                localStorage.setItem('token',res.token)
            }
                console.log(res.data);
                window.location.replace("/home")
            })
    },
    logoutUser(){
        //remove token
        localStorage.removeItem("token");
        window.location.replace('/login');
    }

};
const mutations ={
    setUser(state,data){
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