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
            axios.post("api/login",{
                email:user.email,
                password:user.password
            }).then(res=>{
               
                if(res.data.access_token)
            {
                localStorage.setItem('token',res.data.access_token)
            }

            // console.log(res.data.access_token);
                // console.log(res.data);
                window.location.replace("/home")
            })
    },
    logoutUser(){
        //remove token
        localStorage.removeItem("token");
        window.location.replace("/login");
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