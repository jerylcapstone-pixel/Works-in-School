
package TimeTime;

/**
 *
 * @author Jeryl
 */
public class Attendance {
    private String id,  Name, In, out, dep;
    

    public Attendance() {
        this( null, null, null,null, null);
    }
    public Attendance(String id){
        this(id,null,null, null, null);
    }
     
     
 
    public Attendance ( String id, String Name, String dep, String In, String out ) {
        
        this.id = id;
        this.Name = Name;
        this.In = In;
        this.out = out;
        this.dep = dep;
       
                
       
    }
   
        public Attendance ( String id, String Name, String dep, String In) {
      
        this.id = id;
        this.Name = Name;
        this.dep = dep;
        this.In = In;
      
       
    }
 public Attendance ( String id, String Name, String dep) {
      
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
    

    public void setData( String  id, String Name, String In, String out, String dep ){

        this.id = id;
        this.Name = Name;
        this.In = In;
        this.out = out;
        this.dep = dep;
       

    }
    public void setData( String id, String Name, String In){
       
        this.id = id;
        this.Name = Name;
        this.In = In;
        
    }
     public void setData( String out ){
       
      this.out = out;  
    }
     public void setData1( String id, String Name,String Dep, String out){
      
        this.id = id;
        this.Name = Name;
        this.dep = Dep;
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
    
    

