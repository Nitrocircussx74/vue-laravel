<template>
  <v-app>
    <v-main>
        <routes></routes>
        
    </v-main>
  </v-app>

  <!-- <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Example Component</div>

                    <div class="card-body">
                        <h1>{{$store.getters.getCount}}</h1>
                    </div>

                <div class="card-body">
                     <ComA></ComA>
                </div>

           
   
                
                </div>
            </div>
        </div>
    </div> -->
</template>

<script>
import store from "./store/index";
import ComA from "./components/ComA.vue";
import ComB from "./components/ComB.vue";
export default {
  store,
  name: "app",
  components: {
    ComA,
    ComB,
  },
};
</script>
