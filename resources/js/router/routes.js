import ComA from "../components/ComA.vue";
import Home from "../components/Home.vue";
import ComB from "../components/ComB.vue";

// Vue.use(VueRouter)
const routes =[
    {
        path:'/',
        name:'home',
        component:Home
    },
    {
        path:'/',
        name:'coma',
        component:ComA
    },
    {
        path:'/',
        name:'comb',
        component:ComB
    },
    
    ]