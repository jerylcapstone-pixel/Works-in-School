
package TimeTime;

/**
 *
 * @author Jeryl
 */
public class DataFields {
    private String id,  Name, In, out, dep, pass;
    

    public DataFields() {
        this( null, null, null,null, null, null);
    }
    public DataFields(String id){
        this(id,null,null, null, null,null);
    }
     
     
 
    public DataFields ( String id, String Name, String In, String out, String dep, String pass ) {
        
        this.id = id;
        this.Name = Name;
        this.In = In;
        this.out = out;
        this.dep = dep;
        this.pass = pass;
                
       
    }
   
        public DataFields ( String id, String Name, String dep, String pass) {
      
        this.id = id;
        this.Name = Name;
        this.pass = pass;
        this.dep = dep;
      
       
    }
 public DataFields ( String id, String Name, String dep) {
      
        this.id = id;
        this.Name = Name;
        this.dep = dep;
      
       
    }
    
    public String getId() {
        return this.id;
    }

    public String getName() {
        return this.Name;
    }


    public String getIn() {
        return this.In;
    }
    public String getOut(){
        return this.out;
    }
      public String getDep(){
        return this.dep;
    }
        public String getPass(){
        return this.pass;
    }

    public void setData( String  id, String Name, String In, String out, String dep, String pass){

        this.id = id;
        this.Name = Name;
        this.In = In;
        this.out = out;
        this.dep = dep;
        this.pass = pass;

    }
    public void setData( String id, String Name, String In){
       
        this.id = id;
        this.Name = Name;
        this.In = In;
        
    }
     public void setData1( String id, String Name,String out){
      
        this.id = id;
        this.Name = Name;
         this.out = out;
     }
     public void setId(String id){
         this.id = id;
     }
      public void setName(String Name){
         this.Name = Name;
      }
      public void setDep(String dep){
         this.dep = dep;
      }
}
 
    
    

