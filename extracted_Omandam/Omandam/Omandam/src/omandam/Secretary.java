package omandam;
public class Secretary extends Employee{
    private String email;
    private String cpnum;
     public Secretary(){
        this(null,0,0,null,0,0,null,null);
     }

    public Secretary(String name,int id,int age,String sex,double salary,int year,String email,String cpnum) {
        super(name, id, age, sex,salary,year);
        this.email = email;
        this.cpnum = cpnum;
    
    }
    @Override
    public String getName(){
        return super.getName();
    }
    @Override
    public int getID(){
        return super.getID();
    }
    @Override
    public int getAge(){
        return super.getAge();
    }
    @Override
    public String getSex(){
        return super.getSex();
    }
    @Override
    public double getSalary(){
      double Secretary = super.getSalary();
      return Secretary + 15000;
    }
    @Override
    public int getYear(){
    return super.getYear();
    }
    public String getEmail(){
        return this.email;
    }
    public String getCellnum(){
        return this.cpnum;
    }
   
    public void setData(String name,int id,int age,String sex,double salary,int year,String email, String cpnum){
       super.setData(name, id, age, sex, salary, year);
        this.email = email;
        this.cpnum = cpnum;
    }
    public double bunos(double bunos){
        return super.bonus();
    }
}
