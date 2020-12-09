<template>
  <div class="row justify-content-center">
    <div class="col-md-12">
      <v-card-title>
        <v-text-field
          v-model="search"
          append-icon="mdi-magnify"
          label="Search"
        ></v-text-field>
      </v-card-title>
      <div class="card">
        <div class="card-header">Admin</div>

        <v-data-table
          :headers="headers"
          :items="user_list"
          :search="search"
          class="elevation-1"
        >
          <template v-slot:top>
            <v-toolbar flat>
              <v-toolbar-title>Admin List</v-toolbar-title>
              <v-divider class="mx-4" inset vertical></v-divider>
              <v-spacer></v-spacer>
              <!-- modal -->

              <v-dialog v-model="dialog" persistent max-width="600px">
                <template v-slot:activator="{ on, attrs }">
                  <v-btn color="primary" dark v-bind="attrs" v-on="on">
                    Insert Admin
                  </v-btn>
                </template>
                <v-form ref="entryForm" @submit.prevent="saveUser">
                  <v-card>
                    <v-card-title>
                      <span class="headline">{{ formTitle }}</span>
                    </v-card-title>
                    <!-- <v-form ref="form"> -->
                    <!-- <v-form> -->

                    <v-card-text>
                      <v-container>
                        <v-row>
                          <v-col cols="12" sm="6" md="6">
                            <v-text-field
                              v-model="editedAdmin.name"
                              label="First name*"
                              required
                              :rules="[(v) => !!v || 'Email is required']"
                            ></v-text-field>
                          </v-col>
                          <v-col cols="12" sm="6" md="6">
                            <v-text-field
                              v-model="editedAdmin.phone"
                              label="Phone *"
                              required
                            ></v-text-field>
                          </v-col>
                          <v-col cols="12">
                            <v-text-field
                              v-model="editedAdmin.email"
                              label="Email*"
                              required
                            ></v-text-field>
                          </v-col>
                          <v-col cols="12" sm="6" md="6">
                            <v-text-field
                              v-model="editedAdmin.password"
                              label="Password*"
                              type="password"
                              required
                            ></v-text-field>
                          </v-col>
                          <v-col cols="12" sm="6" md="6">
                            <v-text-field
                              v-model="editedAdmin.password_confirm"
                              label="Confirm Password*"
                              type="password"
                              required
                            ></v-text-field>
                          </v-col>
                          <v-col cols="12">
                            <v-select
                              v-model="editedAdmin.role"
                              :items="item_position"
                              label="Position *"
                              required
                            ></v-select>
                          </v-col>
                        </v-row>
                      </v-container>
                    </v-card-text>

                    <v-card-actions>
                      <v-spacer></v-spacer>
                      <v-btn color="blue darken-1" text @click="dialog = false">
                        Close
                      </v-btn>
                      <v-btn color="blue darken-1" text type="submit">
                        <!-- @click="saveUser" -->
                        Save
                      </v-btn>
                    </v-card-actions>
                  </v-card>
                </v-form>
                <!-- </v-form> -->
              </v-dialog>
              <!-- end modal -->
              <!-- del modal -->
              <v-dialog v-model="dialogDelete" max-width="500px">
                <v-card>
                  <v-card-title class="headline"
                    >Are you sure you want to change status?</v-card-title
                  >
                  <v-card-actions>
                    <v-spacer></v-spacer>
                    <v-btn color="blue darken-1" text @click="closeDelete"
                      >Cancel</v-btn
                    >
                    <v-btn color="blue darken-1" text @click="deleteItemConfirm"
                      >OK</v-btn
                    >
                    <v-spacer></v-spacer>
                  </v-card-actions>
                </v-card>
              </v-dialog>
              <!-- end del modal -->
            </v-toolbar>
          </template>

          <template v-slot:item.actions="{ item }">
            <v-icon small class="mr-2" @click="editItem(item)">
              mdi-pencil
            </v-icon>
            <v-icon small @click="deleteItem(item)"> mdi-delete </v-icon>
          </template>
        </v-data-table>

        <!-- // modal editable -->
      </div>
    </div>
  </div>
</template>
<script>
import userList from "../store/modules/userList";

import { mapGetters, mapActions } from "vuex";
export default {
  data: () => ({
    search: "",
    dialog: false,

    dialogDelete: false,
    item_position: [
      {
        text: "Officer Admin",
        value: 5,
      },
      {
        text: "Sale Admin",
        value: 7,
      },
    ],
    editedIndex: -1,
    editedAdmin: {
      name: "",
      email: "",
      phone: "",
      password: "",
      password_confirm: "",
      role: "",
      fac: "add",
    },
    defaultItem: {
      name: "",
      phone: "",
      email: "",
      password: "",
      password_confirm: "",
      role: "",
      fac: "add",
    },
    user_list: [],
    headers: [
      {
        text: "Name",
        align: "start",
        value: "name",
      },
      { text: "E-Mail", value: "email" },
      { text: "Phone", value: "phone" },
      { text: "Status", value: "active" },
      { text: "Actions", value: "actions", sortable: false },
    ],
    loading: false,
  }),
  mounted() {
    this.getUser();
  },
  // data() {
  //   return {
  //     search: "",
  //     dialog: false,
  //     dialogDelete: false,
  //     item_position: [
  //       {
  //         text: "Officer Admin",
  //         value: 5,
  //       },
  //       {
  //         text: "Sale Admin",
  //         value: 7,
  //       },
  //     ],
  //     editedIndex: -1,
  //     editedAdmin: {
  //       name: "",
  //       email: "",
  //       phone: "",
  //       password: "",
  //       password_confirm: "",
  //       role: "",
  //       fac: "add",
  //     },
  //     defaultItem: {
  //       name: "",
  //       phone: "",
  //       email: "",
  //       password: "",
  //       password_confirm: "",
  //       role: "",
  //       fac: "add",
  //     },
  //     user_list: [],
  //     headers: [
  //       {
  //         text: "Name",
  //         align: "start",
  //         value: "name",
  //       },
  //       { text: "E-Mail", value: "email" },
  //       { text: "Phone", value: "phone" },
  //       { text: "Status", value: "active" },
  //       { text: "Actions", value: "actions", sortable: false },
  //     ],
  //   };
  // },
  computed: {
    formTitle() {
      return this.editedIndex === -1 ? "INSERT ADMIN" : "EDIT ADMIN";
    },
  },
  watch: {
    deep: true,
    dialog(val) {
      val || this.close();
    },
    dialogDelete(val) {
      val || this.closeDelete();
    },
  },
  methods: {
    ...mapActions({
      getuser: "userList/getUser",
      save: "userList/saveAdmin",
      del: "userList/delAdmin",
    }),
    async getUser() {
      this.getuser().then((res) => {
        this.loading = true;
        this.user_list = res.data.user_list;
      });
    },
    saveUser() {
      console.log(this.editedAdmin);
      this.save(this.editedAdmin).then(() => {
        this.getUser();
        this.close();
      });
    },
    deleteItem(item) {
      this.editedIndex = this.user_list.indexOf(item);
      this.editedAdmin = Object.assign({}, item);
      this.dialogDelete = true;
    },

    deleteItemConfirm() {
      this.del(this.editedAdmin).then(() => {
        this.getUser();
        this.closeDelete();
      });
      // this.editedAdmin.splice(this.editedIndex, 1);
    },
    editItem(item) {
      this.editedIndex = this.user_list.indexOf(item);
      console.log(this.editedAdmin);
      this.editedAdmin = Object.assign({}, item);
      this.editedAdmin.fac = "edit";
      this.dialog = true;
    },
    close() {
      this.dialog = false;
      this.$nextTick(() => {
        this.editedAdmin = Object.assign({}, this.defaultItem);
        this.editedIndex = -1;
      });
    },
    closeDelete() {
      this.dialogDelete = false;
      this.$nextTick(() => {
        this.editedAdmin = Object.assign({}, this.defaultItem);
        this.editedIndex = -1;
      });
    },
  },
};
</script>

