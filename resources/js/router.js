import Vue from 'vue'
import VueRouter from 'vue-router'

import Home from "./views/Home.vue";


Vue.use(VueRouter)

const routes =[
    {
        path:'/home',
        // name:'home',
        component:Home
    },
    // {
    //     path:'/',
    //     name:'coma',
    //     component:ComA
    // },
    // {
    //     path:'/',
    //     name:'comb',
    //     component:ComB
    // },
    ]

    // const routes = new VueRouter({
    //     mode:'history',
    //     base:process.env.BASE_URL
    // })

    export default new VueRouter({
        mode:'history',
        routes

    })