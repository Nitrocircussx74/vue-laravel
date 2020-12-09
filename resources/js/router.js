

import Admin from "./views/Home.vue";
import Property from "./views/Property.vue";
import About from "./views/About.vue";
import App from "./App.vue";
import PropertyUnit from "./views/PropertyUnit/PropertyUnitList.vue";


// Vue.use(VueRouter)

export default[
    {
        path:'',
        name:'app',
        component:App
    }, 
    {
        path:'/admin',
        name:'admin',
        component:Admin
    }, 
    {
        path:'/property',
        name:'property',
        component:Property
    }, 
        {
            path:'/about',
            name:'about',
            component:About
        },
        {
            path:'/front/property-unit',
            component:PropertyUnit
        }
        // {
        //     path:'/register',
        //     name:'register',
        //     component:Register
        // },
        // {
        //     path:'/login',
        //     name:'login',
        //     component:Login
        // },
    
]
