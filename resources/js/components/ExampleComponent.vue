<template>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Example Component</div>

                    <div class="card-body">
                        <h1>{{$store.state.count}}</h1>
                    </div>

            <ComA></ComA>
                
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import store from '../store/index'
import ComA from "./ComA.vue"
export default {
  store,
  name: 'app',
  components:{
    ComA
  },

}
</script>
