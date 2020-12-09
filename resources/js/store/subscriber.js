import store from '../store'
import axios from 'axios'

store.subscribe((mutation)=>{
    // console.log(mutation);
    switch(mutation.type){
        case'currentUser/SET_TOKEN':
        if(mutation.payload){
            axios.defaults.headers.common['Authorization'] = 'Bearer ' + mutation.payload
        }
        // console.log(mutation.payload);
        break;
    }
})