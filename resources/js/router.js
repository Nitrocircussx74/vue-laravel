

import Admin from "./views/Admin/Home.vue";
import Property from "./views/Property/Property.vue";
import App from "./App.vue";
import Post from "./views/Post/Post.vue";
import PropertyUnit from "./views/PropertyUnit/PropertyUnitList.vue";



// Vue.use(VueRouter)

function guard(to, from, next){
    if(localStorage.getItem('token')) { 
        // or however you store your logged in state
        next(); // allow to enter route
    } else{
        next('/login'); // go to '/login';
    }
  }
  

export default[
    {
        path:'',
        name:'app',
        component:App
    }, 
    {
        path:'/admin',
        name:'admin',
        component:Admin,
        beforeEnter: guard
    }, 
    {
        path:'/property',
        name:'property',
        component:Property
    },
    {
        path:'/post',
        name:'post',
        component:Post
    },
    {
            path:'/front/property-unit',
            component:PropertyUnit
    },
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
