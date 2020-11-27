<template>
  <!-- <div>
     <h1>v-model</h1>


 </div> -->

  <v-main>
    <Menu></Menu>
    <v-container>
      <div class="row justify-content-center">
        <div class="col-md-8">
          <div class="card">
            <div class="card-header">Example Component</div>

            <div class="card-body"></div>
          </div>
        </div>
      </div>
    </v-container>

    <!--   
   <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Example Component</div>

                    <div class="card-body">
                    </div> 
                </div>
            </div>
        </div>
    </div>  -->
  </v-main>
</template>

<script>
import store from "./store/index";
import Menu from "./components/Menu.vue";
import ComA from "./components/ComA.vue";
import ComB from "./components/ComB.vue";
export default {
  store,
  name: "app",
  components: {
    Menu,
    ComA,
    ComB,
  },
  data() {
    return {
      drawer: true,
      items: [
        { title: "Home", icon: "mdi-home-city" },
        { title: "My Account", icon: "mdi-account" },
        { title: "Users", icon: "mdi-account-group-outline" },
      ],
      mini: true,
    };
  },
};
</script>
