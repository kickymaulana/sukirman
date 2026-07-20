declare module 'vue3-lottie' {
  import { Component, Plugin } from 'vue'
  // Deklarasikan bahwa library ini memiliki named export berupa komponen
  export const VueLottie: Component

  const defaultExport: Plugin
  export default defaultExport
}
