// import Vue from 'vue'
// import VueRouter from 'vue-router'

// import Home from "./views/Home.vue";
// import About from "./views/About.vue";
import Home2 from "./pages/Home.vue";
import Register from "./pages/Register.vue";
import Login from "./pages/Login.vue";

// Vue.use(VueRouter)

export default[
    
        // {
        //     path:'/',
        //     name:'home',
        //     component:Home2
        // }, 
        {
            path:'/register',
            name:'register',
            component:Register
        },
        {
            path:'/login',
            name:'login',
            component:Login
        },
    
]

// const routes =[
//     {
//         path:'/',
//         name:'home',
//         component:Home2
//     },
//     // {
//     //     path:'/home',
//     //     // name:'home',
//     //     component:Home
//     // },
//     // {
//     //     path:'/about',
//     //     // name:'home',
//     //     component:About
//     // },
//     // {
//     //     path:'/',
//     //     name:'coma',
//     //     component:ComA
//     // },
//     // {
//     //     path:'/',
//     //     name:'comb',
//     //     component:ComB
//     // },
//     ]

//     // const routes = new VueRouter({
//     //     mode:'history',
//     //     base:process.env.BASE_URL
//     // })

//     export default new VueRouter({
//         mode:'history',
//         routes

//     })