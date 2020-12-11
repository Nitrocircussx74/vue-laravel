<template>
  <v-container class="my-12">
    <div class="card">
      <div class="card-header">Admin</div>
      <v-card-title>
        <v-text-field
          v-model="search"
          append-icon="mdi-magnify"
          label="Search"
        ></v-text-field>
      </v-card-title>
      <v-data-table
        :headers="headers"
        :items="user_list"
        :search="search"
        item-key="user_list.name"
        class="elevation-1"
        :page.sync="page"
        :items-per-page="itemsPerPage"
        hide-default-footer
        @page-count="pageCount = $event"
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

              <v-card>
                <v-card-title>
                  <span class="headline">{{ formTitle }}</span>
                </v-card-title>
                <v-form ref="form">
                  <v-card-text>
                    <v-container>
                      <v-row>
                        <v-col cols="12" sm="6" md="6">
                          <v-text-field
                            v-model="editedAdmin.name"
                            label="First name*"
                            :rules="rules"
                            hide-details="auto"
                            required
                          ></v-text-field>
                        </v-col>
                        <v-col cols="12" sm="6" md="6">
                          <v-text-field
                            v-model="editedAdmin.phone"
                            label="Phone *"
                            :rules="rules"
                            required
                          ></v-text-field>
                        </v-col>
                        <v-col cols="12">
                          <v-text-field
                            v-model="editedAdmin.email"
                            label="Email*"
                            :rules="rules"
                            :disabled="check"
                            required
                          ></v-text-field>
                        </v-col>
                        <v-col cols="12" sm="6" md="6">
                          <v-text-field
                            v-model="editedAdmin.password"
                            label="Password*"
                            :rules="rules_password"
                            type="password"
                            required
                          ></v-text-field>
                        </v-col>
                        <v-col cols="12" sm="6" md="6">
                          <v-text-field
                            v-model="editedAdmin.password_confirm"
                            label="Confirm Password*"
                            :rules="rules_password"
                            type="password"
                            required
                          ></v-text-field>
                        </v-col>
                        <v-col cols="12">
                          <v-select
                            v-model="editedAdmin.role"
                            :items="item_position"
                            :rules="rules_select"
                            label="Position *"
                            required
                          ></v-select>
                        </v-col>
                      </v-row>
                    </v-container>
                  </v-card-text>
                </v-form>
                <v-card-actions>
                  <v-spacer></v-spacer>
                  <v-btn color="blue darken-1" text @click="dialog = false">
                    Close
                  </v-btn>
                  <v-btn
                    color="blue darken-1"
                    text
                    type="submit"
                    @click="saveUser"
                  >
                    <!-- @click="saveUser" -->
                    Save
                  </v-btn>
                </v-card-actions>
              </v-card>
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
      <div class="text-center pt-2">
        <v-pagination v-model="page" :length="pageCount"></v-pagination>
      </div>

      <!-- // modal editable -->
    </div>
  </v-container>
</template>
<script>
import { mapActions } from "vuex";
export default {
  data: () => ({
    page: 1,
    pageCount: 0,
    itemsPerPage: 10,
    search: "",
    dialog: false,
    check: false,
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
        text: "ลำดับ",
        // align: "start",
        value: "key",
        width: "2%",
      },
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
    formHasErrors: false,
    errorMessages: "",
    rules: [
      (value) => !!value || "Required.",
      (value) => (value && value.length >= 3) || "Min 3 characters",
    ],
    rules_password: [
      (value) => !!value || "Required.",
      (value) => (value && value.length >= 5) || "Min 5 characters",
    ],
    rules_select: [(value) => !!value || "Required."],
  }),

  mounted() {
    this.getUser();
  },
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
    getUser() {
      this.getuser().then((res) => {
        let list = res.data.user_list;
        list.forEach((item, i) => {
          item.key = i + 1;
        });
        this.loading = true;
        this.user_list = list;
      });
    },
    saveUser() {
      console.log(this.editedAdmin);
      console.log(this.$refs.form.validate());
      if (this.editedAdmin.fac == "add") {
        if (this.$refs.form.validate() == true) {
          this.save(this.editedAdmin).then(() => {
            this.getUser();
            this.close();
          });
        }
      } else {
        if (this.$refs.form.validate() == true) {
          this.save(this.editedAdmin).then(() => {
            this.getUser();
            this.close();
          });
        }
      }
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
    },
    editItem(item) {
      this.editedIndex = this.user_list.indexOf(item);
      this.editedAdmin.fac = "edit";
      this.check = true;
      this.rules_password = [];
      this.editedAdmin = Object.assign({}, item);
      this.dialog = true;
    },
    close() {
      this.$refs.form.reset();
      this.dialog = false;
      this.check = false;
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

